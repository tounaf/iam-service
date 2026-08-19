<?php

namespace App\Controller;

use App\Entity\Presence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminPresenceController extends AbstractController
{
    #[Route('/admin/presences', name: 'admin_presences', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): Response
    {
        $presences = $em->getRepository(Presence::class)->findBy([], ['scannedAt' => 'DESC']);

        return $this->render('admin/presences/index.html.twig', [
            'current_route' => 'admin_presences',
            'presences' => $presences,
        ]);
    }
}
