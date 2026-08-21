<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MemberAppController extends AbstractController
{
    #[Route('/espace-membre/{reactRouting}', name: 'member_app_index', requirements: ['reactRouting' => '.*'], defaults: ['reactRouting' => ''])]
    public function index(): Response
    {
        return $this->render('member_app/index.html.twig');
    }
}
