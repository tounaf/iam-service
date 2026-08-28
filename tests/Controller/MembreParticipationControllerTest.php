<?php

namespace App\Tests\Controller;

use App\Controller\MembreParticipationController;
use App\Entity\Evenement;
use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class MembreParticipationControllerTest extends TestCase
{
    public function testMembreParticipationStatsCalculatesLateRateAndDelay(): void
    {
        $membre = new Membre();
        $membre->setNom('Ravelo');
        $membre->setPrenom('Paul');
        $membre->setEmail('paul@example.com');

        $evenement = new Evenement();
        $evenement->setNom('Culte Spécial');
        $evenement->setDateDebut(new \DateTime('2026-03-10 09:00:00'));

        // Scan 15 minutes after start time (09:15)
        $presence = new Presence();
        $presence->setMembre($membre);
        $presence->setActivityName('Culte Spécial');
        $presence->setScannedAt(new \DateTimeImmutable('2026-03-10 09:15:00'));

        $em = $this->createMock(EntityManagerInterface::class);
        $presenceRepo = $this->createMock(EntityRepository::class);
        $evenementRepo = $this->createMock(EntityRepository::class);

        $allActivitiesQuery = $this->createMock(AbstractQuery::class);
        $allActivitiesQuery->method('setParameters')->willReturnSelf();
        $allActivitiesQuery->method('getScalarResult')->willReturn([
            ['activityName' => 'Culte Spécial']
        ]);

        $memberActivitiesQuery = $this->createMock(AbstractQuery::class);
        $memberActivitiesQuery->method('setParameters')->willReturnSelf();
        $memberActivitiesQuery->method('getScalarResult')->willReturn([
            ['activityName' => 'Culte Spécial']
        ]);

        $em->method('createQuery')->willReturnCallback(function ($dql) use ($allActivitiesQuery, $memberActivitiesQuery) {
            if (str_contains($dql, 'WHERE p.membre = :membre')) {
                return $memberActivitiesQuery;
            }
            return $allActivitiesQuery;
        });

        // Mock Presence repository QueryBuilder
        $presenceQb = $this->createMock(QueryBuilder::class);
        $presenceQuery = $this->createMock(AbstractQuery::class);
        $presenceRepo->method('createQueryBuilder')->with('p')->willReturn($presenceQb);
        $presenceQb->method('where')->willReturnSelf();
        $presenceQb->method('andWhere')->willReturnSelf();
        $presenceQb->method('orderBy')->willReturnSelf();
        $presenceQb->method('setParameter')->willReturnSelf();
        $presenceQb->method('getQuery')->willReturn($presenceQuery);
        $presenceQuery->method('getResult')->willReturn([$presence]);

        // Mock Evenement repository QueryBuilder
        $evenementQb = $this->createMock(QueryBuilder::class);
        $evenementQuery = $this->createMock(AbstractQuery::class);
        $evenementRepo->method('createQueryBuilder')->with('e')->willReturn($evenementQb);
        $evenementQb->method('where')->willReturnSelf();
        $evenementQb->method('setParameter')->willReturnSelf();
        $evenementQb->method('getQuery')->willReturn($evenementQuery);
        $evenementQuery->method('getResult')->willReturn([$evenement]);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($presenceRepo, $evenementRepo) {
            return match ($entityClass) {
                Presence::class => $presenceRepo,
                Evenement::class => $evenementRepo,
                default => null,
            };
        });

        $controller = new MembreParticipationController($em);
        $request = Request::create('/api/membres/1/participation-stats?year=2026');

        $response = $controller($membre, $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(100.0, $data['participationRate']);
        $this->assertEquals(1, $data['lateCount']);
        $this->assertEquals(100.0, $data['lateRate']);
        $this->assertTrue($data['presenceLogs'][0]['isLate']);
        $this->assertEquals(15, $data['presenceLogs'][0]['delayMinutes']);
    }
}
