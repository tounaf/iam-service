<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class PresenceScanController extends AbstractController
{
    public function __construct(
        private Environment $twig
    ) {}

    #[Route('/membres/scan/{token}', name: 'presence_scan', methods: ['GET', 'POST'])]
    public function __invoke(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        ?TokenStorageInterface $tokenStorage = null
    ): Response {
        $membre = $entityManager->getRepository(Membre::class)->findOneBy(['qrCodeToken' => $token]);

        $format = $request->query->get('format');
        $acceptHeader = $request->headers->get('Accept', '');
        $contentTypeHeader = $request->headers->get('Content-Type', '');

        $isJsonRequest = $format === 'json'
            || str_contains($acceptHeader, 'application/json')
            || str_contains($contentTypeHeader, 'application/json');

        if (!$membre) {
            if ($isJsonRequest) {
                return new JsonResponse([
                    'error' => 'Code QR non reconnu',
                    'message' => 'Le code QR scanné ne correspond à aucun membre enregistré.'
                ], Response::HTTP_NOT_FOUND);
            }

            return new Response($this->renderErrorHtml(
                'Code QR non reconnu',
                'Le code QR scanné ne correspond à aucun membre enregistré.'
            ), Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'text/html; charset=utf-8'
            ]);
        }

        if ($request->isMethod('POST')) {
            $activityName = trim((string) $request->request->get('activityName', ''));
            if ($activityName === '' && str_contains($contentTypeHeader, 'application/json')) {
                $payload = json_decode($request->getContent(), true);
                if (is_array($payload) && isset($payload['activityName'])) {
                    $activityName = trim((string) $payload['activityName']);
                }
            }

            if ($activityName === '') {
                if ($isJsonRequest) {
                    return new JsonResponse([
                        'error' => 'Veuillez saisir ou choisir le nom de l\'activité.'
                    ], Response::HTTP_BAD_REQUEST);
                }

                return new Response($this->renderFormHtml(
                    $membre,
                    'Veuillez saisir ou choisir le nom de l\'activité.'
                ), Response::HTTP_BAD_REQUEST, [
                    'Content-Type' => 'text/html; charset=utf-8'
                ]);
            }

            // Create and persist presence
            $presence = new Presence();
            $presence->setMembre($membre);
            $presence->setActivityName($activityName);
            $scannedAt = new \DateTimeImmutable();
            $presence->setScannedAt($scannedAt);

            // If a coordinator is logged in, register scannedBy
            if ($tokenStorage !== null) {
                $tokenObj = $tokenStorage->getToken();
                $user = $tokenObj?->getUser();
                if ($user instanceof Membre) {
                    $presence->setScannedBy($user);
                }
            }

            $entityManager->persist($presence);
            $entityManager->flush();

            // Invalidate cached attendance stats for this member
            try {
                if ($this->container && $this->container->has(\App\Service\AttendanceStatsService::class)) {
                    $this->container->get(\App\Service\AttendanceStatsService::class)->invalidateMemberCache($membre, (int)$scannedAt->format('Y'));
                }
            } catch (\Throwable $e) {}

            if ($isJsonRequest) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Présence enregistrée avec succès.',
                    'presence' => [
                        'id' => $presence->getId(),
                        'activityName' => $activityName,
                        'scannedAt' => $scannedAt->format(\DateTimeInterface::ATOM),
                    ],
                    'membre' => [
                        'id' => $membre->getId(),
                        'nom' => $membre->getNom(),
                        'prenom' => $membre->getPrenom(),
                    ]
                ], Response::HTTP_OK);
            }

            return new Response($this->renderSuccessHtml(
                $membre,
                $activityName
            ), Response::HTTP_OK, [
                'Content-Type' => 'text/html; charset=utf-8'
            ]);
        }

        if ($isJsonRequest) {
            return new JsonResponse([
                'membre' => [
                    'id' => $membre->getId(),
                    'nom' => $membre->getNom(),
                    'prenom' => $membre->getPrenom(),
                    'fiangonana' => $membre->getFiangonana()?->getNom() ?? 'Paroisse',
                ]
            ], Response::HTTP_OK);
        }

        return new Response($this->renderFormHtml($membre), Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }

    private function renderFormHtml(Membre $membre, ?string $error = null): string
    {
        $nomComplet = $membre->getPrenom() . ' ' . $membre->getNom();
        $fiangonanaNom = $membre->getFiangonana() ? $membre->getFiangonana()->getNom() : 'Paroisse';

        return $this->twig->render('presence/form.html.twig', [
            'nomComplet' => $nomComplet,
            'fiangonanaNom' => $fiangonanaNom,
            'error' => $error,
        ]);
    }

    private function renderSuccessHtml(Membre $membre, string $activityName): string
    {
        $nomComplet = $membre->getPrenom() . ' ' . $membre->getNom();
        $date = (new \DateTimeImmutable())->format('d/m/Y à H:i');

        return $this->twig->render('presence/success.html.twig', [
            'nomComplet' => $nomComplet,
            'activityName' => $activityName,
            'date' => $date,
        ]);
    }

    private function renderErrorHtml(string $title, string $message): string
    {
        return $this->twig->render('presence/error.html.twig', [
            'title' => $title,
            'message' => $message,
        ]);
    }
}
