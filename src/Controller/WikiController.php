<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\WikiPage;
use App\Repository\WikiPageRepository;
use App\Helper\ContentHelper;
use App\Exception\DatabaseException;
use App\Exception\UnknownException;

use ArangoDBClient\Collection as ArangoCollection;
use ArangoDBClient\CollectionHandler as ArangoCollectionHandler;

use ArangoDBClient\DocumentHandler as ArangoDocumentHandler;
use ArangoDBClient\Document as ArangoDocument;
use ArangoDBClient\Exception as ArangoException;
use ArangoDBClient\Export as ArangoExport;
use ArangoDBClient\ConnectException as ArangoConnectException;
use ArangoDBClient\ClientException as ArangoClientException;
use ArangoDBClient\ServerException as ArangoServerException;
use ArangoDBClient\Statement as ArangoStatement;
use ArangoDBClient\UpdatePolicy as ArangoUpdatePolicy;

use ArangoDBClient\Edge as ArangoEdge;
use ArangoDBClient\EdgeHandler as ArangoEdgeHandler;
use ArangoDBClient\GraphHandler as ArangoGraphHandler;
use ArangoDBClient\Graph as ArangoGraph;
use ArangoDBClient\EdgeDefinition as ArangoEdgeDefinition;
use ArangoDBClient\Vertex as ArangoVertex;


class WikiController extends AbstractController
{

    

    public function __construct()
    {
       
    }


    /**
     * @Route("/save", methods={"POST"}, name="wiki_save" )
     */
    public function save(Request $request)
    {
        $errors = [];

        $slug = $request->request->get('slug');
        if (!$slug) {
            $slug = 'main';
        }
        $parentSlug = $request->request->get('parentSlug');
        if (!$parentSlug) {
            $parentSlug = 'main';
        }
        
        $content = $request->request->get('content');

        $repos = new WikiPageRepository($this->getConfig());

        // first step: create wiki page
        try {
            
            $isPage = $repos->isPage($slug,''); // does page exist, independently of status
            if (!$isPage) {
                // create new page object
                $page = new WikiPage($this->getConfig(), ['slug' => $slug, 'status' => 'active', 'parentSlug' => $parentSlug, 'content' => $content]);
            } else {
                $page = $repos->getBySlug($slug);
                $page->parentSlug = $parentSlug; //current parent, multiple parents with graph stuff
                $page->content = $content;
                $page->status = 'active';
            }
            $page->save();
        
        } catch (DatabaseException $e) {
            $errors[] = 'Fehler bei der Datenbankverbindung. Fehlermeldung: ' . $e->getMessage();
        } catch (\Exception $e) {
            $errors[] = 'Allgemeiner Fehler. Fehlermeldung: ' . $e->getMessage();
        }

        if (!$errors) {

            // second step: create pages to be linked to or adjust sorting parameter
            $links = ContentHelper::getPageLinks($content);
        

            if ($links) {
                try {
                    foreach ($links as $link) {
                        $isLinkPage = $repos->isPage($link,'');
                    
                        if (!$isLinkPage) {
                            $linkPage = new WikiPage($this->getConfig(), ['slug' => $link, 'parentSlug' => $slug]);
                            $linkPage->save();
                            $parentSort = 1;
                            
                        } else {
                            // find out parent sort
                            $linkPage = $repos->getBySlug($link);
                            $parentSort = $repos->maxParentSort($linkPage->id);
                            $parentSort += 1;
                        }
                    
                        $page->saveLinkTo($linkPage, $parentSort); 
                    }
                } catch (DatabaseException $e) {
                    $errors[] = 'Fehler bei der Datenbankverbindung. Fehlermeldung: ' . $e->getMessage();
                } catch (\Exception $e) {
                    $errors[] = 'Allgemeiner Fehler. Fehlermeldung: ' . $e->getMessage();
                }
            }
        }
        // todo: find dangling link_to edges, i.e. delete old entries, compare old with new, find out which to delete

        // todo (maybe): if recreating a deleted page, check all previous edges (links) to this 
        // page, check content for links like "[[xyz]]" which are currently linking the updated and saved page,
        // update the links (edges), if is still connected. But change the sequence, i.e. use the new parent as parent, sort the remaining

        if ($errors) {
            foreach ($errors as $error) {
                $this->addFlash(
                    'error', $error
                );
            }
        } else {
            $this->addFlash(
                'success',
                'Die Wiki-Seite wurde gespeichert!'
            );
    
        }

        return $this->redirectToRoute('wiki_show', ['slug' => $slug]);
      
       
    }



    /**
     * @Route("/delete", methods={"POST"})
     */
    public function deleteAction(Request $request)
    {
        $slug = $request->request->get('slug');
        if (!$slug) {
            $slug = 'main';
        }

        $repos = new WikiPageRepository($this->getConfig());

       
        try {
            $isPage = $repos->isPage($slug);
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

        if (!$isPage) {
             return $this->render('error.html.twig', [
                'error' => 'Die zu löschende Seite existiert nicht.',
              
            ]);    
        } 

        try {

            $page = $repos->getBySlug($slug);
            $page->content = '';
            $page->status = 'deleted'; 
            // keep parentSlug, it's needed for updating edge values
            $page->save();
            
            $page->deleteCleanupEdge();
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }
        
        try {
            $breadcrumb = $repos->getBreadcrumb($page->parentSlug);
            
        } catch (DatabaseException $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        } catch (\Exception $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        }
      
        
        
        return $this->render('show_deleted.html.twig', [
            'slug' => $page->parentSlug,
            'breadcrumb' => $breadcrumb
        ]);  



    }


    public function getConfig()
    {
        $configValues = ['endpoint' => $this->getParameter('arangodb.endpoint'),
        'database' => $this->getParameter('arangodb.database'),
        'user' => $this->getParameter('arangodb.user')];
        return $configValues;
    }

      
    /**
    * Show wiki page
    *
    * @Route("/wiki/{slug?}", name="wiki_show")
    */
    public function show(Request $request, $slug) // string hint not possible, because it could be null... 
    {
  
        if (!$slug) {
            $slug = 'main';
        }
        $pageOut = [];

     
        $repos = new WikiPageRepository($this->getConfig() );
      
        try {
            $isPage = $repos->isPage($slug);
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


        if (!$isPage) {

            // no page, so show create page formular
            return $this->form($request, $slug);

        }

        try {
            $page = $repos->getBySlug($slug);
          
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
        $content = $page->content;     
        $content = ContentHelper::parseFirst($content);
              
        $parser = new \ParsedownExtra();
        $content = $parser->text($content);
        $pageOut['content'] = $this->parseWiki($slug, $content);

        $pageOut['slug'] = $page->slug;
        $pageOut['parentSlug'] = $page->parentSlug;
      

        try {
            $breadcrumb = $repos->getBreadcrumb($slug);
        } catch (DatabaseException $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        } catch (\Exception $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        }
      
        $pageOut['breadcrumb'] = $breadcrumb;

        return $this->render('show.html.twig', [
            'page' => $pageOut
        ]);    


    }
    

    /**
     * Show wiki page input formular
     * 
     *  @Route("/form/{slug?}", name="page_form")
    */
    public function form(Request $request, $slug, $parentSlug='')
    {
        $cancelToParent = false;
      
        if (!$slug) {
            $slug = 'main';
        }
        // get parentSlug from query string
        if (!$parentSlug) {
            $parentSlug = $request->get('parent');
            if ($parentSlug) {
                // if parentSlug exists, we assume a new page will be created
                $cancelToParent = true;
            }
        }
       

        $repos = new WikiPageRepository($this->getConfig());

        try {
            $isPage = $repos->isPage($slug);
            if (!$isPage) {
            
                $page = new WikiPage($this->getConfig(), ['slug' => $slug, 'parentSlug' => $parentSlug]);    
            } else {
                $page = $repos->getBySlug($slug);
            }
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


        $pageOut['content'] = $page->content;  

        $pageOut['slug'] = $page->slug;
        $pageOut['parentSlug'] = $page->parentSlug;

     
        try {
            $breadcrumb = $repos->getBreadcrumb($slug);
        } catch (DatabaseException $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        } catch (\Exception $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        }
          
        $pageOut['breadcrumb'] = $breadcrumb;
      

        return $this->render('form.html.twig', [
            'page' => $pageOut,
            'cancelToParent' => $cancelToParent
        ]);       
    }


    /**
    *  @Route("/delete/{slug?}", name="page_delete", methods="GET")
    */
    public function deleteForm(Request $request, $slug, $parentSlug='')
    {
      
        if (!$slug) {
            $slug = 'main';
        }
        // get parentSlug from query string
        if (!$parentSlug) {
            $parentSlug = $request->get('parent');
            //var_dump($parentSlug);
        }


        $repos = new WikiPageRepository($this->getConfig());

        $isPage = $repos->isPage($slug);

        if (!$isPage) {
            
            echo "page does not exist, could not be deleted";
            return false;
        } else {
            $page = $repos->getBySlug($slug);

        }
        
        $pageOut['content'] = $page->content;  

        $pageOut['slug'] = $page->slug;
        $pageOut['parentSlug'] = $page->parentSlug;

        try {
            $breadcrumb = $repos->getBreadcrumb($slug);
        } catch (DatabaseException $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        } catch (\Exception $e) {
            // fail silently, breadcrumb will not be displayed
            $breadcrumb = null;            
        }
          
        $pageOut['breadcrumb'] = $breadcrumb;

        return $this->render('form_delete.html.twig', [
            'page' => $pageOut
        ]);       
    }


    /**
     * Find URL references in wiki syntax and transform them into clickable URLs
     */
    public function parseWiki($parentSlug, $text) 
    {
        $repos = new WikiPageRepository($this->getConfig());

        $text = preg_replace_callback('/(\[\[(.*?)\]\])/',function($ma) use ($repos , $parentSlug) {
            $slug = $ma[2];
            $isPage = $repos->isPage($slug);
            $queryString = '';
            if (!$isPage) { // create URL with parentSlug parameter
                $url = $this->generateUrl('wiki_show',  ['slug' => $ma[2],'parent' => $parentSlug]);
            } else {
                $url = $this->generateUrl('wiki_show', ['slug' => $ma[2]]);
            }
          
            $re = '<a href="' . $url . '">' . $ma[2] . '</a>';
          
            return $re;


        }, $text);

        return $text;

    }

  
}