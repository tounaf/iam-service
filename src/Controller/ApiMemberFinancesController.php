<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Cotisation;
use App\Entity\Don;
use App\Entity\Fiangonana;
use App\Entity\Groupe;
use App\Entity\Membre;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiMemberFinancesController extends AbstractController
{
    #[Route('/api/membres/{id}/finances', name: 'api_membre_finances_get', methods: ['GET'])]
    public function getFinances(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            return $this->json(['message' => 'Membre introuvable'], Response::HTTP_NOT_FOUND);
        }

        $year = (int) ($request->query->get('year') ?: date('Y'));

        // Fetch cotisations for this member for the selected year
        $cotisations = $em->getRepository(Cotisation::class)->findBy([
            'membre' => $membre,
            'annee' => $year
        ], ['paidAt' => 'DESC']);

        // Fetch dons for this member
        $dons = $em->getRepository(Don::class)->findBy([
            'membre' => $membre
        ], ['paidAt' => 'DESC']);

        // Compute monthly payment matrix (12 months x 4 installments)
        $monthsMatrix = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthsMatrix[$m] = [
                'mois' => $m,
                'tranches' => [1 => null, 2 => null, 3 => null, 4 => null],
                'isFullyPaid' => false,
                'totalPaid' => 0.0,
            ];
        }

        $totalCotisationsYear = 0.0;
        $monthsPaidCount = 0;

        foreach ($cotisations as $c) {
            $m = $c->getMois();
            $t = $c->getTranche();
            $val = (float) $c->getMontant();

            if ($m >= 1 && $m <= 12 && $t >= 1 && $t <= 4) {
                $monthsMatrix[$m]['tranches'][$t] = [
                    'id' => $c->getId(),
                    'montant' => $val,
                    'paidAt' => $c->getPaidAt()?->format('d/m/Y H:i'),
                    'entityType' => $c->getAssociation() ? 'Association: ' . $c->getAssociation()->getNom() : ($c->getGroupe() ? 'Zone: ' . $c->getGroupe()->getNom() : 'Paroisse'),
                ];
                $monthsMatrix[$m]['totalPaid'] += $val;
                $totalCotisationsYear += $val;
            }
        }

        // Count months that have at least one payment
        foreach ($monthsMatrix as $m => $data) {
            if ($data['totalPaid'] > 0) {
                $monthsPaidCount++;
            }
        }

        $donsList = [];
        $totalDons = 0.0;
        foreach ($dons as $d) {
            $val = (float) $d->getMontant();
            $totalDons += $val;
            $donsList[] = [
                'id' => $d->getId(),
                'montant' => $val,
                'libelle' => $d->getLibelle() ?: 'Don libre',
                'paidAt' => $d->getPaidAt()?->format('d/m/Y H:i'),
                'entityType' => $d->getAssociation() ? 'Association: ' . $d->getAssociation()->getNom() : ($d->getGroupe() ? 'Zone: ' . $d->getGroupe()->getNom() : 'Paroisse'),
            ];
        }

        return $this->json([
            'membre' => [
                'id' => $membre->getId(),
                'nom' => $membre->getNom(),
                'prenom' => $membre->getPrenom(),
            ],
            'year' => $year,
            'monthsPaidCount' => $monthsPaidCount,
            'totalCotisationsYear' => $totalCotisationsYear,
            'totalDons' => $totalDons,
            'monthsMatrix' => array_values($monthsMatrix),
            'dons' => $donsList,
        ]);
    }

    #[Route('/api/membres/{id}/cotisations/add', name: 'api_membre_cotisation_add', methods: ['POST'])]
    public function addCotisation(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();

        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            return $this->json(['message' => 'Membre introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?: $request->request->all();

        $annee = isset($data['annee']) ? (int)$data['annee'] : (int)date('Y');
        $mois = isset($data['mois']) ? (int)$data['mois'] : (int)date('n');
        $tranche = isset($data['tranche']) ? (int)$data['tranche'] : 1;
        $montant = isset($data['montant']) ? (float)$data['montant'] : 0.0;
        $contextType = $data['contextType'] ?? 'fiangonana';
        $contextId = isset($data['contextId']) ? (int)$data['contextId'] : null;

        if ($montant <= 0) {
            return $this->json(['message' => 'Le montant doit être supérieur à zéro.'], Response::HTTP_BAD_REQUEST);
        }

        if ($mois < 1 || $mois > 12) {
            return $this->json(['message' => 'Mois invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if ($tranche < 1 || $tranche > 4) {
            return $this->json(['message' => 'La tranche doit être comprise entre 1 et 4.'], Response::HTTP_BAD_REQUEST);
        }

        $cotisation = new Cotisation();
        $cotisation->setMembre($membre);
        $cotisation->setAnnee($annee);
        $cotisation->setMois($mois);
        $cotisation->setTranche($tranche);
        $cotisation->setMontant((string)$montant);
        $cotisation->setPaidAt(new \DateTimeImmutable());

        if ($currentUser instanceof Membre) {
            $cotisation->setEnregistrePar($currentUser);
        }

        if ($contextType === 'association' && $contextId) {
            $cotisation->setAssociation($em->getRepository(Association::class)->find($contextId));
        } elseif ($contextType === 'groupe' && $contextId) {
            $cotisation->setGroupe($em->getRepository(Groupe::class)->find($contextId));
        } elseif ($membre->getFiangonana()) {
            $cotisation->setFiangonana($membre->getFiangonana());
        }

        $em->persist($cotisation);
        $em->flush();

        return $this->json([
            'message' => 'Cotisation enregistrée avec succès !',
            'cotisation' => [
                'id' => $cotisation->getId(),
                'annee' => $cotisation->getAnnee(),
                'mois' => $cotisation->getMois(),
                'tranche' => $cotisation->getTranche(),
                'montant' => (float)$cotisation->getMontant(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/api/membres/{id}/dons/add', name: 'api_membre_don_add', methods: ['POST'])]
    public function addDon(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var Membre|null $currentUser */
        $currentUser = $this->getUser();

        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            return $this->json(['message' => 'Membre introuvable'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?: $request->request->all();

        $montant = isset($data['montant']) ? (float)$data['montant'] : 0.0;
        $libelle = trim($data['libelle'] ?? '');
        $contextType = $data['contextType'] ?? 'fiangonana';
        $contextId = isset($data['contextId']) ? (int)$data['contextId'] : null;

        if ($montant <= 0) {
            return $this->json(['message' => 'Le montant du don doit être supérieur à zéro.'], Response::HTTP_BAD_REQUEST);
        }

        $don = new Don();
        $don->setMembre($membre);
        $don->setMontant((string)$montant);
        $don->setLibelle($libelle ?: 'Don libre');
        $don->setPaidAt(new \DateTimeImmutable());

        if ($currentUser instanceof Membre) {
            $don->setEnregistrePar($currentUser);
        }

        if ($contextType === 'association' && $contextId) {
            $don->setAssociation($em->getRepository(Association::class)->find($contextId));
        } elseif ($contextType === 'groupe' && $contextId) {
            $don->setGroupe($em->getRepository(Groupe::class)->find($contextId));
        } elseif ($membre->getFiangonana()) {
            $don->setFiangonana($membre->getFiangonana());
        }

        $em->persist($don);
        $em->flush();

        return $this->json([
            'message' => 'Don enregistré avec succès !',
            'don' => [
                'id' => $don->getId(),
                'montant' => (float)$don->getMontant(),
                'libelle' => $don->getLibelle(),
            ]
        ], Response::HTTP_CREATED);
    }
}
