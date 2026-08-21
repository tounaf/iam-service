<?php

namespace App\Tests\Controller;

use App\Controller\AdminEvenementController;
use App\Entity\Association;
use App\Entity\Evenement;
use App\Entity\Media;
use App\Entity\Membre;
use App\Entity\Note;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

class AdminEvenementControllerTest extends TestCase
{
    private function createMockContainer(Environment $twig = null): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->willReturnCallback(function ($id) {
            return in_array($id, ['request_stack', 'twig', 'router'], true);
        });

        $container->method('get')->willReturnCallback(function ($id) use ($twig) {
            if ($id === 'request_stack') {
                $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
                $session = new Session(new MockArraySessionStorage());
                $request = new Request();
                $request->setSession($session);
                $requestStack->push($request);
                return $requestStack;
            }
            if ($id === 'twig' && $twig) {
                return $twig;
            }
            if ($id === 'router') {
                $router = $this->createMock(\Symfony\Component\Routing\Generator\UrlGeneratorInterface::class);
                $router->method('generate')->willReturn('/admin/evenements/1');
                return $router;
            }
            return null;
        });

        return $container;
    }

    public function testShowRendersEventDetailsAndCalculatesParticipationRate(): void
    {
        $association = new Association();
        $association->setNom('STK');

        $member1 = new Membre();
        $member1->setNom('Ratsimba');
        $member1->setPrenom('Nirina');

        $member2 = new Membre();
        $member2->setNom('Rakoto');
        $member2->setPrenom('Jean');

        $association->addMembre($member1);
        $association->addMembre($member2);

        $evenement = new Evenement();
        $evenement->setNom('Formation des Jeunes');
        $evenement->setAssociation($association);

        $presence = new Presence();
        $presence->setMembre($member1);
        $presence->setActivityName('Formation des Jeunes');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $presenceRepo = $this->createMock(EntityRepository::class);

        $eventRepo->method('find')->with(1)->willReturn($evenement);
        $presenceRepo->method('findBy')->with(['activityName' => 'Formation des Jeunes'], ['scannedAt' => 'DESC'])->willReturn([$presence]);

        $em->method('getRepository')->willReturnCallback(function ($class) use ($eventRepo, $presenceRepo) {
            return match ($class) {
                Evenement::class => $eventRepo,
                Presence::class => $presenceRepo,
                default => null,
            };
        });

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('admin/evenements/show.html.twig', $this->callback(function ($context) {
                return $context['presentCount'] === 1
                    && $context['totalScopeMembers'] === 2
                    && $context['tauxParticipation'] === 50.0;
            }))
            ->willReturn('Rendered detail view');

        $controller = new AdminEvenementController();
        $controller->setContainer($this->createMockContainer($twig));

        $response = $controller->show(1, $em);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testUpdateCompteRenduIndependently(): void
    {
        $evenement = new Evenement();
        $evenement->setNom('Formation');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $eventRepo->method('find')->with(1)->willReturn($evenement);

        $em->method('getRepository')->with(Evenement::class)->willReturn($eventRepo);
        $em->expects($this->once())->method('flush');

        $controller = new AdminEvenementController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/evenements/1/compte-rendu', 'POST', [
            'compte_rendu' => 'Compte-rendu autonome.'
        ]);

        $response = $controller->updateCompteRendu(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('Compte-rendu autonome.', $evenement->getCompteRendu());
    }

    public function testAddNoteIndependently(): void
    {
        $evenement = new Evenement();
        $evenement->setNom('Formation');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $eventRepo->method('find')->with(1)->willReturn($evenement);

        $em->method('getRepository')->with(Evenement::class)->willReturn($eventRepo);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $controller = new AdminEvenementController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/evenements/1/add-note', 'POST', [
            'new_note' => 'Excellent'
        ]);

        $response = $controller->addNote(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('Excellent', $evenement->getNotes()->first()->getContenu());
    }

    public function testUploadMediaWithUrlIndependently(): void
    {
        $evenement = new Evenement();
        $evenement->setNom('Formation');

        $em = $this->createMock(EntityManagerInterface::class);
        $eventRepo = $this->createMock(EntityRepository::class);
        $eventRepo->method('find')->with(1)->willReturn($evenement);

        $em->method('getRepository')->with(Evenement::class)->willReturn($eventRepo);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $controller = new AdminEvenementController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/evenements/1/upload-media', 'POST', [
            'media_url' => 'https://example.com/photo.jpg'
        ]);

        $response = $controller->uploadMedia(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertContains('https://example.com/photo.jpg', $evenement->getMediaUrls());
    }

    public function testNotesAndMediasCollectionsHandledSafely(): void
    {
        $evenement = new Evenement();

        $this->assertCount(0, $evenement->getNotes());
        $this->assertCount(0, $evenement->getMedias());

        $note = new Note();
        $note->setContenu('Excellent');
        $evenement->addNote($note);

        $media = new Media();
        $media->setUrl('/uploads/events/photo.jpg');
        $media->setType('image');
        $evenement->addMedia($media);

        $this->assertCount(1, $evenement->getNotes());
        $this->assertCount(1, $evenement->getMedias());
        $this->assertEquals(['Excellent'], $evenement->getNotesAsArray());
        $this->assertEquals(['/uploads/events/photo.jpg'], $evenement->getMediaUrls());
    }
}
