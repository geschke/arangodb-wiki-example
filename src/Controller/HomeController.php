<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;


class HomeController extends AbstractController
{

  
    public function __construct()
    {
       
        
    }

  
    /**
     * @Route("/", name="app_homepage")
     */
    public function show(Request $request)
    {
        return $this->render('home.html.twig');    
    }




}