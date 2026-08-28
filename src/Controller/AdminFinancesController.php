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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class AdminFinancesController extends AbstractController
{
    #[Route('/admin/membres/{id}/add-cotisation', name: 'admin_membre_add_cotisation', methods: ['POST'])]
    public function addCotisation(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        $annee = (int) $request->request->get('annee', date('Y'));
        $mois = (int) $request->request->get('mois', date('n'));
        $tranche = (int) $request->request->get('tranche', 1);
        $montant = (float) $request->request->get('montant', 0);
        $contextType = $request->request->get('context_type', 'fiangonana');
        $contextId = $request->request->get('context_id');

        if ($montant > 0 && $mois >= 1 && $mois <= 12 && $tranche >= 1 && $tranche <= 4) {
            $cotisation = new Cotisation();
            $cotisation->setMembre($membre);
            $cotisation->setAnnee($annee);
            $cotisation->setMois($mois);
            $cotisation->setTranche($tranche);
            $cotisation->setMontant((string)$montant);
            $cotisation->setPaidAt(new \DateTimeImmutable());

            /** @var Membre|null $currentUser */
            $currentUser = $this->getUser();
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

            $this->addFlash('success', sprintf('Cotisation de %.2f pour le mois %d (Tranche %d) enregistrée !', $montant, $mois, $tranche));
        } else {
            $this->addFlash('error', 'Données de cotisation invalides.');
        }

        return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId(), 'tab' => 'finances']);
    }

    #[Route('/admin/membres/{id}/add-don', name: 'admin_membre_add_don', methods: ['POST'])]
    public function addDon(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $membre = $em->getRepository(Membre::class)->find($id);
        if (!$membre) {
            throw new NotFoundHttpException('Membre introuvable.');
        }

        $montant = (float) $request->request->get('montant', 0);
        $libelle = trim($request->request->get('libelle', ''));
        $contextType = $request->request->get('context_type', 'fiangonana');
        $contextId = $request->request->get('context_id');

        if ($montant > 0) {
            $don = new Don();
            $don->setMembre($membre);
            $don->setMontant((string)$montant);
            $don->setLibelle($libelle ?: 'Don libre');
            $don->setPaidAt(new \DateTimeImmutable());

            /** @var Membre|null $currentUser */
            $currentUser = $this->getUser();
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

            $this->addFlash('success', sprintf('Don de %.2f enregistré avec succès !', $montant));
        } else {
            $this->addFlash('error', 'Le montant du don doit être supérieur à 0.');
        }

        return $this->redirectToRoute('admin_membre_edit', ['id' => $membre->getId(), 'tab' => 'finances']);
    }
}
