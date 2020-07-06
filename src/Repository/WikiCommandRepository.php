<?php

namespace App\Repository;


use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
use ArangoDBClient\Graph;
use ArangoDBClient\GraphHandler;
use ArangoDBClient\EdgeDefinition;

class WikiCommandRepository
{

    private $config;

    private $router;

    private $connection;

    protected $collectionName = 'wiki';
    protected $edgeName = 'pageEdge';
    protected $graphName = 'pageGraph';

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



    public function createCollection()
    {
        try {
            // create a new collection
           
            $collection = new Collection($this->collectionName);
            $collectionHandler = new CollectionHandler($this->connection);
        
            if ($collectionHandler->has($this->collectionName)) {
                // drops an existing collection with the same name to make
                // tutorial repeatable
                $collectionHandler->drop($this->collectionName);
            }
        
            $collectionId = $collectionHandler->create($collection);
            $documentHandler = new DocumentHandler($this->connection);
        
         
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

    public function createGraph()
    {
    
        try {
            // Setup connection, graph and graph handler
            
            $graphHandler = new GraphHandler($this->connection);
            $graph        = new Graph();
            $graph->set('_key', $this->graphName);
            $graph->addEdgeDefinition(EdgeDefinition::createDirectedRelation($this->edgeName, $this->collectionName, $this->collectionName));
            /*try {
                $graphHandler->dropGraph($graph);
            } catch (\Exception $e) {
                // graph may not yet exist. ignore this error for now
            }*/
            $graphHandler->createGraph($graph);
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