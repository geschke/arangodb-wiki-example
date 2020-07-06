<?php

namespace App\Entity;

use App\Repository\WikiPageRepository as WikiPageRepository;
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

class WikiPage 
{
    public $id;

    public $content;

    public $slug;

    public $parentSlug;

    public $connection;

    protected $collectionName = "wiki";

    public function __construct(array $config, array $data=[])
    {
        $this->config = $config;
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
        
        
        $this->connection = new Connection($connectionOptions);
        
        $this->id = isset($data['id']) ? $data['id'] : '';
        $this->content = isset($data['content']) ? $data['content'] : '';
        $this->slug = isset($data['slug']) ? $data['slug'] : '';
        $this->parentSlug = isset($data['parentSlug']) ? $data['parentSlug'] : '';
        $this->status = isset($data['status']) ? $data['status'] : 'new'; // status: new, active, deleted
        

    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->task = $task;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function getParentSlug()
    {
        return $this->parentSlug;
    }

    public function setParentSlug($parentSlug)
    {
        $this->parentSlug = $parentSlug;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }


    /**
     * Create or update wiki page in database
     */
    public function save()
    {
       
        $documentHandler = new DocumentHandler($this->connection);

        if ($this->id) {
            try {
                // update document
                $page = $documentHandler->get($this->collectionName, $this->id);
                $page->set('content', $this->content);
                $page->set('parentSlug', $this->parentSlug);
                $page->set('status', $this->status);
                $page->set('updated', date('Y-m-d H:i:s'));
                // slug will never be updated
                $result = $documentHandler->update($page);

            
                // update incoming edge, if parentSort is 0
                $this->updateIncomingEdgeIfDeleted($this->parentSlug);
            } catch ( ConnectException $e) {
                throw new DatabaseException($e->getMessage());
            } catch ( ServerException $e) {
                throw new DatabaseException($e->getMessage());
            } catch ( ClientException $e) {
                throw new DatabaseException($e->getMessage());
            } catch (\Exception $e) {
                throw new UnknownException($e->getMessage());
            }
            
        } else { 
            try {
                // create new document
                $page = new Document();
            
                // use set method to set document properties
                $page->setInternalKey($this->slug); // maybe don't set slug as value
                $page->set('slug', $this->slug);
                $page->set('parentSlug', $this->parentSlug);
                $page->set('status', $this->status);
                $page->set('content', $this->content);
                $page->set('created', date('Y-m-d H:i:s'));
            
                // send the document to the server
                $this->id = $documentHandler->save($this->collectionName, $page);

                $parent = $documentHandler->get($this->collectionName, $this->parentSlug);

                // store parent edge connection
                $edgeHandler = new EdgeHandler($this->connection);
                $linkToEdgeDefinition = Edge::createFromArray(['parentSort' => 1]); // per definition first incoming edge

                $result = $edgeHandler->saveEdge('pageEdge', $parent->getHandle(), $page->getHandle(), $linkToEdgeDefinition);
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
       
        return true;
    }

    /**
     * Update incoming edge from parentSlug, but only if parentSort value is 0, i.e. it was previously deleted.
     * Otherwise don't change sorting!
     */
    public function updateIncomingEdgeIfDeleted($parentSlug)
    {
        $documentHandler = new DocumentHandler($this->connection);
        $edgeHandler = new EdgeHandler($this->connection);

        try {
            $parent = $documentHandler->get($this->collectionName, $parentSlug);
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }
    
        
        $query = "FOR e IN pageEdge
        FILTER e._to == @to
        AND e._from == @from
        FILTER e.parentSort == 0
        RETURN e";

      
        $bindVars = ['to' => $this->id, 'from' => $parent->getHandle()];

        try {


            $statement = new  Statement($this->connection, [
                    'query'     => $query,
                    'count'     => true,
                    'batchSize' => 1000,
                    'bindVars'  => $bindVars,
                    'sanitize'  => true,
                ]
            );
            
            $cursor = $statement->execute();
         
            if ($cursor->getCount() > 0) {
              

                $parentSort = 1;
                $edge = $cursor->current(); // only one edge possible per definition

                $repos = new WikiPageRepository($this->config);
                $currentParentSort = $repos->maxParentSort($this->id);
              
                $edge->parentSort = $currentParentSort + 1;
                $edgeHandler->update($edge);
              
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

        return true;

    }


    public function saveLinkTo($linkToPage,$parentSort = 1)
    {
        try {
            
            $collection = new Collection($this->collectionName);
            $documentHandler = new DocumentHandler($this->connection);
            $edgeHandler = new EdgeHandler($this->connection);

            $repos = new WikiPageRepository($this->config);

            // todo: does $this->id exist?


            // current page is parent
            $fromPage = $documentHandler->get($this->collectionName, $this->id);
        
            $toPage = $documentHandler->get($this->collectionName, $linkToPage->id);

        
            // check if link_to from parent to child exists, only create when not previously created

            $edges = $edgeHandler->edges('pageEdge',$fromPage->getHandle(),'out');

            //var_dump($edges);
            foreach ($edges as $edge) {
            

                // todo: if parentSort is 0, update edge, 
                // if parentSort is > 0, i.e. it is sorted, return

                if ($edge->getTo() == $toPage->getHandle()  && $edge->parentSort > 0) {
                    return;
        
                }
                if ($edge->getTo() == $toPage->getHandle() && $edge->parentSort == 0) {
                // update with current
                $currentParentSort = $repos->maxParentSort($edge->getTo());
                $edge->parentSort = $currentParentSort + 1;
                $edgeHandler->update($edge);
                return;
        
            }
            
                
            }
            // if no edge is set, then create new edge
                
            $linkToEdgeDefinition =  Edge::createFromArray(['parentSort' => $parentSort]);

            $result = $edgeHandler->saveEdge('pageEdge', $fromPage->getHandle(), $toPage->getHandle(), $linkToEdgeDefinition);
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }        

        return true;
    }

    public function deleteCleanupEdge()
    {
        try {

            // $this->id exists
            // get all outgoing links
            // with vertices do: 
            
            $edgeHandler = new  EdgeHandler($this->connection);
            $documentHandler = new  DocumentHandler($this->connection);

            $deletePage = $documentHandler->get($this->collectionName, $this->id);

            // first step: find outgoing edges
            $edges = $edgeHandler->edges('pageEdge',$deletePage->getHandle(),'out');

            foreach ($edges as $edge) {
            
                // find vertex of outgoing edge 
                $vertexHandler = $edge->getTo();
            
                $vertex = $documentHandler->get($this->collectionName,$vertexHandler);
            

                // delete edge to vertex
                $this->deleteEdge($edge);
                $this->sortIncomingEdges($vertex); // sort incoming edge of vertex which is connected by outgoing edge of vertex which should be deleted

            }

            // second step: set parentSort of incoming edges to 0

            $edges = $edgeHandler->edges('pageEdge',$deletePage->getHandle(),'in');

            
            foreach ($edges as $edge) {
            
                $edge->parentSort = 0;
                $edgeHandler->update($edge);

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

        return true;
    }

    public function deleteEdge($edge)
    {
        // delete submitted incoming edge and sort other incoming edges by parentSort 
        try {
            $edgeHandler = new EdgeHandler($this->connection);
            $edgeHandler->remove($edge); // temporarily disabled
        } catch ( ConnectException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ServerException $e) {
            throw new DatabaseException($e->getMessage());
        } catch ( ClientException $e) {
            throw new DatabaseException($e->getMessage());
        } catch (\Exception $e) {
            throw new UnknownException($e->getMessage());
        }
        return true;

    }


    public function sortIncomingEdges($vertex)
    {
        $edgeHandler = new EdgeHandler($this->connection);
        
        $query = "FOR e IN pageEdge
        FILTER e._to == @to 
        AND e.parentSort > 0
        SORT e.parentSort ASC
        RETURN e";
     

        $bindVars = ['to' => $vertex->getHandle()];
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
           
            $parentSort = 1;
            foreach ($cursor->getAll() as $doc) {
               
                $doc->parentSort = $parentSort++;
                $edgeHandler->update($doc);

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

        return true;
     
    }

}