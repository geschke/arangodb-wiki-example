<?php

namespace App\Repository;


use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use App\Entity\WikiPage;
use App\Exception\DatabaseException;
use App\Exception\UnknownException;


use ArangoDBClient\Collection;
use ArangoDBClient\CollectionHandler;
use ArangoDBClient\Connection;
use ArangoDBClient\ConnectionOptions;
use ArangoDBClient\DocumentHandler;
use ArangoDBClient\Document;
use ArangoDBClient\Exception;
use ArangoDBClient\Export;
use ArangoDBClient\ConnectException;
use ArangoDBClient\ClientException;
use ArangoDBClient\ServerException;
use ArangoDBClient\Statement;
use ArangoDBClient\UpdatePolicy;

use ArangoDBClient\Edge;
use ArangoDBClient\EdgeHandler;

class WikiPageRepository
{

    private $config;

    private $router;

    private $connection;

    protected $collectionName = 'wiki';

    public function __construct(array $config, UrlGeneratorInterface $router = null)
    {
        $this->config = $config;
        $this->router = $router;
        // todo. get options from environment or global config
        $connectionOptions = [
            // database name
            // ConnectionOptions::OPTION_DATABASE => '_system',
            // server endpoint to connect to
            ConnectionOptions::OPTION_ENDPOINT => $config['endpoint'],
            // authorization type to use (currently supported: 'Basic')
            // ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            // user for basic authorization
            ConnectionOptions::OPTION_AUTH_USER => $config['user'],
            // password for basic authorization
            // ConnectionOptions::OPTION_AUTH_PASSWD => '',
            // connection persistence on server. can use either 'Close' (one-time connections) or 'Keep-Alive' (re-used connections)
            ConnectionOptions::OPTION_CONNECTION => 'Keep-Alive',
            // connect timeout in seconds
            // ConnectionOptions::OPTION_TIMEOUT => 30,
            // whether or not to reconnect when a keep-alive connection has timed out on server
            // ConnectionOptions::OPTION_RECONNECT => true,
            // optionally create new collections when inserting documents
            ConnectionOptions::OPTION_CREATE => true,
            // optionally create new collections when inserting documents
            // ConnectionOptions::OPTION_UPDATE_POLICY =>  UpdatePolicy::LAST,
            
            ConnectionOptions::OPTION_DATABASE => $config['database']
        ];
        
        
        // turn on exception logging (logs to whatever PHP is configured)
        Exception::enableLogging();
        
         // todo: senseful error handling
        try {
            $this->connection = new Connection($connectionOptions);

        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }
    
     
    }

    public function getBySlug(string $slug)
    {
        $page = new WikiPage($this->config, ['slug' => $slug]);    
        
        
        try {
            $documentHandler = new DocumentHandler($this->connection);

            $doc = $documentHandler->get($this->collectionName, $slug);
        
            if (!$doc) {
                return null;
            }

            /*$edgeHandler = new  EdgeHandler($this->connection);
            $edges = $edgeHandler->edges('pageEdge',$doc->getHandle());
            foreach ($edges as $edge) {
                var_dump($edge->getFrom());
                var_dump($edge->getTo());
                echo "--------------------";
            }*/
            //var_dump($edges);

            //echo "document found\n<br>";
            //echo "key: " . $doc->getKey() . "<br>";
            //echo "internal Key: " . $doc->getInternalKey() . "<br>";
            //echo "internal ID: " . $doc->getInternalId() . "<br>";
            
            $page = new WikiPage($this->config, [
                'id' => $doc->getId(),
                'key' => $doc->getInternalKey(),
                'slug' => $slug, 
            'parentSlug' => $doc->get('parentSlug'),
            'content' => $doc->get('content'),
            'created' => $doc->get('created'),
            'updated' => $doc->get('updated'),
            'status' => $doc->get('status')
            ]);    
            //var_dump($page);
            return $page;
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
            //print $e . PHP_EOL;
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
            //print $e . PHP_EOL;
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
            //print $e . PHP_EOL;
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }

    }


    public function getBreadcrumb(string $slug)
    {
        $pageOut = [];

        try {
            $isPage = $this->isPage($slug, '');
            if (!$isPage) {
                return null;
            }

            $toPage = $this->getBySlug($slug);
            if (!$toPage) {
                  return null;
            }
        } 
        catch (DatabaseException $e) {
            throw new DatabaseException($e->getMessage());
           
        } catch (UnknownException $e) {
            throw new UnknownException($e->getMessage());
        }
        

        // get 15 levels of 
        $query = "FOR v,e,p IN 1..15 INBOUND @to GRAPH 'pageGraph' OPTIONS {'uniqueVertices': 'path'} FILTER p.edges[*].parentSort ALL == 1 RETURN v";
        
        $bindVars = ['to' => $toPage->id];

      
        try {
         
            $statement = new Statement($this->connection, [
                    'query'     => $query,
                    'count'     => true,
                    'batchSize' => 1000,
                    'bindVars'  => $bindVars,
                    'sanitize'  => true,
                ]
            );
        
            $cursor = $statement->execute();
           
            foreach ($cursor->getAll() as $doc) {
               
                $pageOut[] = $doc;
            }
         

        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }

        return array_reverse($pageOut);
    }

    /**
     * Does a page with submitted slug exist?
     * 
     * Currently this is an alias of getBySlug, maybe find a better / efficient way
     */
    public function isPage(string $slug,$status = 'active')
    {
        
        
        try {
            $documentHandler = new DocumentHandler($this->connection);
            $isPage = $documentHandler->has($this->collectionName, $slug);
          
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }

        if (!$isPage) {
            return false;
        }

        try {
            $page = $documentHandler->get($this->collectionName, $slug);
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }
        
        if ($status != '' && $page->status != $status) {
            return false;
        }
        return true;

    }

    /**
     * Get maximum number of pages linked to a page
     */
    public function maxParentSort($toId)
    {
      $maxParentSort = 0;

      $query = "FOR e IN pageEdge FILTER e._to == @id FILTER e.parentSort > 0 COLLECT AGGREGATE maxParentSort = MAX(e.parentSort) RETURN { maxParentSort }";
     
      $bindVars = ['id' => $toId];
      
      try {


          $statement = new Statement($this->connection, [
                  'query'     => $query,
                  'count'     => true,
                  'batchSize' => 1000,
                  'bindVars'  => $bindVars,
                  'sanitize'  => true,
              ]
          );
        
          $cursor = $statement->execute();
        
          $result = $cursor->current();
          // result is Document object with value maxParentSort
          // due to AQL query it can be only one document, not multiple
          $maxParentSort = ($result->maxParentSort == null ? 0 : intval($result->maxParentSort));
       
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }

        return $maxParentSort;
    }

    
  

}