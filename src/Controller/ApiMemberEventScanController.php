<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Membre;
use App\Entity\Presence;
use App\Entity\TypeEvenement;
use App\Entity\Association;
use App\Entity\Groupe;
use App\Entity\Fiangonana;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiMemberEventScanController extends AbstractController
{
    #[Route('/api/member-events/create', name: 'api_member_events_create', methods: ['POST'])]
    public function createEvent(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?: [];

        $nom = trim($data['nom'] ?? '');
        $description = trim($data['description'] ?? '');
        $lieu = trim($data['lieu'] ?? '');
        $associationId = isset($data['associationId']) ? (int)$data['associationId'] : null;
        $groupeId = isset($data['groupeId']) ? (int)$data['groupeId'] : null;
        $typeEvenementId = isset($data['typeEvenementId']) ? (int)$data['typeEvenementId'] : null;

        if ($nom === '') {
            return $this->json(['message' => 'Le nom de l\'événement est obligatoire'], Response::HTTP_BAD_REQUEST);
        }

        $evenement = new Evenement();
        $evenement->setNom($nom);
        $evenement->setDescription($description ?: null);
        $evenement->setLieu($lieu ?: null);

        if (isset($data['dateDebut']) && $data['dateDebut']) {
            try {
                $evenement->setDateDebut(new \DateTime($data['dateDebut']));
            } catch (\Exception $e) {}
        }
        if (isset($data['dateFin']) && $data['dateFin']) {
            try {
                $evenement->setDateFin(new \DateTime($data['dateFin']));
            } catch (\Exception $e) {}
        }

        if ($typeEvenementId) {
            $type = $em->getRepository(TypeEvenement::class)->find($typeEvenementId);
            if ($type) {
                $evenement->setTypeEvenement($type);
            }
        }

        if ($associationId) {
            $assoc = $em->getRepository(Association::class)->find($associationId);
            if ($assoc) {
                $evenement->setAssociation($assoc);
            }
        } elseif ($groupeId) {
            $groupe = $em->getRepository(Groupe::class)->find($groupeId);
            if ($groupe) {
                $evenement->setGroupe($groupe);
            }
        } elseif ($currentUser->getFiangonana()) {
            $evenement->setFiangonana($currentUser->getFiangonana());
        }

        $em->persist($evenement);
        $em->flush();

        return $this->json([
            'message' => 'Événement créé avec succès !',
            'evenement' => [
                'id' => $evenement->getId(),
                'nom' => $evenement->getNom(),
                'description' => $evenement->getDescription(),
                'lieu' => $evenement->getLieu(),
                'dateDebut' => $evenement->getDateDebut()?->format(\DateTimeInterface::ATOM),
                'association' => $evenement->getAssociation() ? ['id' => $evenement->getAssociation()->getId(), 'nom' => $evenement->getAssociation()->getNom()] : null,
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/member-events/{id}/scan', name: 'api_member_events_scan', methods: ['POST'])]
    public function scanQrCode(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            return $this->json(['message' => 'Événement introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?: [];
        $qrCodeToken = trim($data['qrCodeToken'] ?? '');

        if ($qrCodeToken === '') {
            return $this->json(['message' => 'Token QR Code manquant'], Response::HTTP_BAD_REQUEST);
        }

        $targetMembre = $em->getRepository(Membre::class)->findOneBy(['qrCodeToken' => $qrCodeToken]);
        if (!$targetMembre) {
            return $this->json(['message' => 'Membre non trouvé pour ce QR Code'], Response::HTTP_NOT_FOUND);
        }

        // Check if member is already marked present for this event
        $existingPresence = $em->getRepository(Presence::class)->findOneBy([
            'membre' => $targetMembre,
            'activityName' => $evenement->getNom(),
        ]);

        if ($existingPresence) {
            return $this->json([
                'message' => sprintf('Le membre %s %s est déjà marqué présent pour cet événement.', $targetMembre->getPrenom(), $targetMembre->getNom()),
                'membre' => [
                    'id' => $targetMembre->getId(),
                    'nom' => $targetMembre->getNom(),
                    'prenom' => $targetMembre->getPrenom(),
                    'photoUrl' => $targetMembre->getPhotoUrl(),
                ],
                'alreadyPresent' => true,
            ]);
        }

        $presence = new Presence();
        $presence->setMembre($targetMembre);
        $presence->setActivityName($evenement->getNom());
        $presence->setScannedAt(new \DateTimeImmutable());

        $em->persist($presence);
        $em->flush();

        return $this->json([
            'message' => sprintf('Présence de %s %s validée avec succès !', $targetMembre->getPrenom(), $targetMembre->getNom()),
            'membre' => [
                'id' => $targetMembre->getId(),
                'nom' => $targetMembre->getNom(),
                'prenom' => $targetMembre->getPrenom(),
                'photoUrl' => $targetMembre->getPhotoUrl(),
            ],
            'scannedAt' => $presence->getScannedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/api/member-events/{id}/attendees', name: 'api_member_events_attendees', methods: ['GET'])]
    public function getEventAttendees(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['message' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }

        $evenement = $em->getRepository(Evenement::class)->find($id);
        if (!$evenement) {
            return $this->json(['message' => 'Événement introuvable'], Response::HTTP_NOT_FOUND);
        }

        $presences = $em->getRepository(Presence::class)->findBy(
            ['activityName' => $evenement->getNom()],
            ['scannedAt' => 'DESC']
        );

        $attendees = [];
        foreach ($presences as $p) {
            $m = $p->getMembre();
            if ($m) {
                $attendees[] = [
                    'id' => $m->getId(),
                    'nom' => $m->getNom(),
                    'prenom' => $m->getPrenom(),
                    'email' => $m->getEmail(),
                    'telephone' => $m->getTelephone(),
                    'photoUrl' => $m->getPhotoUrl(),
                    'scannedAt' => $p->getScannedAt()?->format('d/m/Y H:i:s'),
                ];
            }
        }

        return $this->json([
            'eventId' => $evenement->getId(),
            'eventNom' => $evenement->getNom(),
            'count' => count($attendees),
            'attendees' => $attendees,
        ]);
    }
}
