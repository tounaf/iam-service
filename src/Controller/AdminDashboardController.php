<?php

namespace App\Controller;

use App\Entity\Association;
use App\Entity\Groupe;
use App\Entity\Membre;
use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_index', methods: ['GET'])]
    #[Route('/admin/dashboard', name: 'admin_dashboard', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): Response
    {
        $totalMembres = $em->getRepository(Membre::class)->count([]);
        $totalGroupes = $em->getRepository(Groupe::class)->count([]);
        $totalAssociations = $em->getRepository(Association::class)->count([]);
        $totalPresences = $em->getRepository(Presence::class)->count([]);

        $recentPresences = $em->getRepository(Presence::class)->findBy([], ['scannedAt' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'current_route' => 'admin_dashboard',
            'totalMembres' => $totalMembres,
            'totalGroupes' => $totalGroupes,
            'totalAssociations' => $totalAssociations,
            'totalPresences' => $totalPresences,
            'recentPresences' => $recentPresences,
        ]);
    }
}
