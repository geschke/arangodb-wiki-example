<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

use App\Repository\WikiCommandRepository;



class DbCommandController extends AbstractController
{


    public function __construct()
    {
       
    }

        
    /**
    * @Route("/db/create_graph")
    */
    public function createGraph()
    {

        $repos = new WikiCommandRepository($this->getConfig());
        try {
            $success = $repos->createGraph();
        } catch (DatabaseException $e) {
            return $this->render('error.html.twig', [
                'error' => 'Fehler bei der Datenbankverbindung.',
                'error_orig' => $e->getMessage()
            ]);    
        } catch (\Exception $e) {
            return $this->render('error.html.twig', [
                'error' => 'Allgemeiner Fehler.',
                'error_orig' => $e->getMessage()
            ]);    
        }

        return new Response(
            '<html><body>pageGraph created!</body></html>'
        );
    }
   

    /**
    * @Route("/db/create_wiki")
    */
    public function createCollection()
    {

        $repos = new WikiCommandRepository($this->getConfig());
        try {
            $success = $repos->createCollection();
        } catch (DatabaseException $e) {
            return $this->render('error.html.twig', [
                'error' => 'Fehler bei der Datenbankverbindung.',
                'error_orig' => $e->getMessage()
            ]);    
        } catch (\Exception $e) {
            return $this->render('error.html.twig', [
                'error' => 'Allgemeiner Fehler.',
                'error_orig' => $e->getMessage()
            ]);    
        }

        return new Response(
            '<html><body>collection created!</body></html>'
        );

      
    }

    
    public function getConfig()
    {
        $configValues = ['endpoint' => $this->getParameter('arangodb.endpoint'),
        'database' => $this->getParameter('arangodb.database'),
        'user' => $this->getParameter('arangodb.user')];
        return $configValues;
    }

}
