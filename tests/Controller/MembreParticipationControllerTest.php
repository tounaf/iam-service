<?php

namespace App\Tests\Controller;

use App\Controller\MembreParticipationController;
use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembreParticipationControllerTest extends TestCase
{
    public function testInvokeReturnsCorrectStatsAndLogs(): void
    {
        $member = new Membre();
        $member->setNom('Rabe');
        $member->setPrenom('Jean');
        $member->setEmail('jean.rabe@example.com');

        $scannedBy = new Membre();
        $scannedBy->setNom('Admin');
        $scannedBy->setPrenom('Boss');

        $presence1 = new Presence();
        $presence1->setMembre($member);
        $presence1->setActivityName('Formation Jeunes 2026');
        $presence1->setScannedBy($scannedBy);

        $presence2 = new Presence();
        $presence2->setMembre($member);
        $presence2->setActivityName('Culte Sabbat');
        $presence2->setScannedBy(null);

        $presencesList = [$presence1, $presence2];

        // Mock Entity Manager and Queries
        $entityManager = $this->createMock(EntityManagerInterface::class);

        // We will create two Query mocks
        $queryAll = $this->createMock(Query::class);
        $queryAll->expects($this->once())
            ->method('setParameters')
            ->willReturnSelf();
        $queryAll->expects($this->once())
            ->method('getScalarResult')
            ->willReturn([
                ['activityName' => 'Formation Jeunes 2026'],
                ['activityName' => 'Culte Sabbat'],
                ['activityName' => 'Réunion de prière'],
                ['activityName' => 'Social Outreach']
            ]);

        $queryMember = $this->createMock(Query::class);
        $queryMember->expects($this->once())
            ->method('setParameters')
            ->willReturnSelf();
        $queryMember->expects($this->once())
            ->method('getScalarResult')
            ->willReturn([
                ['activityName' => 'Formation Jeunes 2026'],
                ['activityName' => 'Culte Sabbat']
            ]);

        // Map createQuery to return the appropriate query mocks in sequence
        $entityManager->expects($this->exactly(2))
            ->method('createQuery')
            ->willReturnMap([
                ['SELECT DISTINCT p.activityName FROM App\Entity\Presence p WHERE p.scannedAt >= :startDate AND p.scannedAt <= :endDate', $queryAll],
                ['SELECT DISTINCT p.activityName FROM App\Entity\Presence p WHERE p.membre = :membre AND p.scannedAt >= :startDate AND p.scannedAt <= :endDate', $queryMember]
            ]);

        // Mock Presence Repository and Query Builder for detailed logs
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryDetailed = $this->createMock(AbstractQuery::class);

        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('p')
            ->willReturn($queryBuilder);

        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($queryDetailed);

        $queryDetailed->expects($this->once())
            ->method('getResult')
            ->willReturn($presencesList);

        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Presence::class)
            ->willReturn($repository);

        $controller = new MembreParticipationController($entityManager);

        $request = new Request(['year' => '2026']);
        $response = $controller->__invoke($member, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);

        // Verification of calculated statistics
        $this->assertEquals(2026, $data['year']);
        $this->assertEquals(4, $data['totalActivitiesCount']);
        $this->assertEquals(2, $data['attendedActivitiesCount']);
        $this->assertEquals(50.0, $data['participationRate']); // (2 / 4) * 100 = 50.0%

        // Verify member details in response
        $this->assertEquals('Rabe', $data['membre']['nom']);
        $this->assertEquals('Jean', $data['membre']['prenom']);

        // Verify detailed logs
        $this->assertCount(2, $data['presenceLogs']);
        $this->assertEquals('Formation Jeunes 2026', $data['presenceLogs'][0]['activityName']);
        $this->assertEquals('Admin', $data['presenceLogs'][0]['scannedBy']['nom']);
        $this->assertEquals('Culte Sabbat', $data['presenceLogs'][1]['activityName']);
        $this->assertNull($data['presenceLogs'][1]['scannedBy']);
    }

    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $controller = new MembreParticipationController($entityManager);
        $controller->__invoke(null, new Request());
    }
}
