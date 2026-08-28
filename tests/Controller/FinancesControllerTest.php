<?php

namespace App\Tests\Controller;

use App\Controller\AdminFinancesController;
use App\Controller\ApiMemberFinancesController;
use App\Entity\Association;
use App\Entity\Cotisation;
use App\Entity\Don;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class FinancesControllerTest extends TestCase
{
    private function createMockContainer(): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);

        $container->method('has')->willReturnCallback(function ($id) {
            return in_array($id, ['request_stack', 'router', 'serializer', 'serializer.encoder.json', 'security.token_storage'], true);
        });
        $container->method('get')->willReturnCallback(function ($id) {
            if ($id === 'request_stack') {
                $requestStack = new RequestStack();
                $session = new Session(new MockArraySessionStorage());
                $request = new Request();
                $request->setSession($session);
                $requestStack->push($request);
                return $requestStack;
            }
            if ($id === 'router') {
                $router = $this->createMock(UrlGeneratorInterface::class);
                $router->method('generate')->willReturnCallback(function ($name, $params = []) {
                    return '/admin/membres/' . ($params['id'] ?? 1) . '/editer?tab=finances';
                });
                return $router;
            }
            if ($id === 'serializer') {
                return new class {
                    public function serialize($data, $format, $context = []) {
                        return json_encode($data);
                    }
                };
            }
            if ($id === 'security.token_storage') {
                return new class {
                    public function getToken() {
                        return null;
                    }
                };
            }
            return null;
        });

        return $container;
    }

    public function testAddCotisationInFourInstallments(): void
    {
        $membre = new Membre();
        $membre->setNom('Rabe');
        $membre->setPrenom('Jean');

        $em = $this->createMock(EntityManagerInterface::class);
        $membreRepo = $this->createMock(EntityRepository::class);

        $membreRepo->method('find')->with(1)->willReturn($membre);
        $em->method('getRepository')->with(Membre::class)->willReturn($membreRepo);

        $em->expects($this->once())->method('persist')->with($this->callback(function ($c) use ($membre) {
            return $c instanceof Cotisation
                && $c->getMembre() === $membre
                && $c->getMois() === 3
                && $c->getTranche() === 2
                && (float)$c->getMontant() === 5000.0;
        }));
        $em->expects($this->once())->method('flush');

        $controller = new AdminFinancesController();
        $controller->setContainer($this->createMockContainer());

        $request = Request::create('/admin/membres/1/add-cotisation', 'POST', [
            'annee' => 2026,
            'mois' => 3,
            'tranche' => 2,
            'montant' => '5000',
            'context_type' => 'fiangonana',
        ]);

        $response = $controller->addCotisation(1, $request, $em);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testApiMemberFinancesSummary(): void
    {
        $membre = new Membre();
        $membre->setNom('Rasoa');
        $membre->setPrenom('Marie');

        $cotisation = new Cotisation();
        $cotisation->setMembre($membre);
        $cotisation->setAnnee(2026);
        $cotisation->setMois(1);
        $cotisation->setTranche(1);
        $cotisation->setMontant('10000');

        $don = new Don();
        $don->setMembre($membre);
        $don->setMontant('25000');
        $don->setLibelle('Soutien Travaux');

        $em = $this->createMock(EntityManagerInterface::class);
        $membreRepo = $this->createMock(EntityRepository::class);
        $cotisationRepo = $this->createMock(EntityRepository::class);
        $donRepo = $this->createMock(EntityRepository::class);

        $membreRepo->method('find')->with(5)->willReturn($membre);
        $cotisationRepo->method('findBy')->willReturn([$cotisation]);
        $donRepo->method('findBy')->willReturn([$don]);

        $em->method('getRepository')->willReturnCallback(function ($entityClass) use ($membreRepo, $cotisationRepo, $donRepo) {
            return match ($entityClass) {
                Membre::class => $membreRepo,
                Cotisation::class => $cotisationRepo,
                Don::class => $donRepo,
                default => null,
            };
        });

        $controller = new ApiMemberFinancesController();
        $controller->setContainer($this->createMockContainer());
        $request = Request::create('/api/membres/5/finances?year=2026');

        $response = $controller->getFinances(5, $request, $em);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(1, $data['monthsPaidCount']);
        $this->assertEquals(10000.0, $data['totalCotisationsYear']);
        $this->assertEquals(25000.0, $data['totalDons']);
    }
}
