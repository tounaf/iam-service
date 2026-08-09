<?php

namespace App\Tests\Controller;

use App\Controller\MembreStatsController;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\Fiangonana;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MembreStatsControllerTest extends TestCase
{
    public function testInvokeThrowsNotFoundForNullMember(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Membre non trouvé.');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $controller = new MembreStatsController();
        $controller->__invoke(null, $entityManager);
    }

    public function testInvokeReturnsCorrectStatsForValidMember(): void
    {
        $fiangonana = new Fiangonana();
        $fiangonana->setNom('Test Church');

        $member = $this->createMock(Membre::class);
        $member->method('getId')->willReturn(123);
        $member->method('getNom')->willReturn('Ratsimbazafy');
        $member->method('getPrenom')->willReturn('Nirina');
        $member->method('getEmail')->willReturn('nirina@example.com');
        $member->method('getFiangonana')->willReturn($fiangonana);

        $presence1 = new Presence();
        $presence1->setActivityName('Formation 1');
        $presence1->setScannedAt(new \DateTimeImmutable('2025-01-01T10:00:00Z'));

        $presence2 = new Presence();
        $presence2->setActivityName('Formation 1');
        $presence2->setScannedAt(new \DateTimeImmutable('2025-01-02T10:00:00Z'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $presenceRepo = $this->createMock(EntityRepository::class);

        // Mock the findBy call
        $presenceRepo->method('findBy')
            ->with(['membre' => $member], ['scannedAt' => 'DESC'])
            ->willReturn([$presence1, $presence2]);

        // Mock the QueryBuilder for the distinct query
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleColumnResult')->willReturn(['Formation 1', 'Formation 2', 'Formation 3']);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $presenceRepo->method('createQueryBuilder')
            ->with('p')
            ->willReturn($qb);

        $entityManager->method('getRepository')
            ->with(Presence::class)
            ->willReturn($presenceRepo);

        $controller = new MembreStatsController();
        $response = $controller->__invoke($member, $entityManager);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);

        // Verify member details
        $this->assertEquals(123, $data['membre']['id']);
        $this->assertEquals('Ratsimbazafy', $data['membre']['nom']);
        $this->assertEquals('Nirina', $data['membre']['prenom']);

        // Verify stats
        $this->assertEquals(2, $data['statistics']['totalPresences']);
        $this->assertEquals(3, $data['statistics']['totalUniqueSystemActivities']);
        $this->assertEquals(1, $data['statistics']['memberUniqueActivitiesCount']);
        // 1 / 3 = 33.33%
        $this->assertEquals(33.33, $data['statistics']['participationRate']);
        $this->assertEquals(['Formation 1' => 2], $data['statistics']['presencesByActivity']);

        // Verify presences list
        $this->assertCount(2, $data['presences']);
        $this->assertEquals('Formation 1', $data['presences'][0]['activityName']);
    }
}
