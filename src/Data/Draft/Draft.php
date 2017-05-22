<?php
/** App\Data\Draft class */
namespace App\Data;

use GuzzleHttp\Client as GuzzleClient;

/**
 * The model class.
 *
 * @author Joost De Cock <joost@decock.org>
 * @copyright 2017 Joost De Cock
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, Version 3
 */
class Draft
{
    /** @var \Slim\Container $container The container instance */
    protected $container;

    /** @var int $id Unique id of the draft */
    private $id;

    /** @var int $user ID of the user owning the draft */
    private $user;

    /** @var string $pattern Pattern of which this is a draft */
    private $pattern;

    /** @var int $model ID of the model for which this was drafted */
    private $model;

    /** @var string $name Name of the draft */
    private $name;

    /** @var string $handle Unique handle of the draft */
    private $handle;

    /** @var string $svg The drafted SVG */
    private $svg;

    /** @var string $compared The compared SVG */
    private $compared;

    /** @var string $data Other app data stored as JSON */
    private $data;

    /** @var string $created The time the draft was created */
    private $created;

    /** @var bool $shared Whether the draft is shared */
    private $shared;

    /** @var string $notes the draft notes */
    private $notes;


    // constructor receives container instance
    public function __construct(\Slim\Container $container) 
    {
        $this->container = $container;
    }

    public function getId() 
    {
        return $this->id;
    } 

    public function getUser() 
    {
        return $this->user;
    } 

    private function setUser($user) 
    {
        $this->user = $user;
        return true;
    } 

    public function getModel() 
    {
        return $this->model;
    } 

    private function setModel($model) 
    {
        $this->model = $model;
        return true;
    } 

    public function getPattern() 
    {
        return $this->pattern;
    } 

    private function setPattern($pattern) 
    {
        $this->pattern = $pattern;
        return true;
    } 

    private function setHandle($handle) 
    {
        $this->handle = $handle;
        return true;
    } 

    public function getHandle() 
    {
        return $this->handle;
    } 

    public function setName($name) 
    {
        $this->name = $name;
        return true;
    } 

    public function getName() 
    {
        return $this->name;
    } 

    public function setSvg($svg) 
    {
        $this->svg = $svg;
        return true;
    } 

    public function getSvg() 
    {
        return $this->svg;
    } 

    public function setCompared($compared) 
    {
        $this->compared = $compared;
        return true;
    } 

    public function getCompared() 
    {
        return $this->compared;
    } 

    public function setNotes($notes) 
    {
        $this->notes = $notes;
        return true;
    } 

    public function getNotes() 
    {
        return $this->notes;
    } 

    public function setShared($shared) 
    {
        $this->shared = $shared;
        return true;
    } 

    public function getShared() 
    {
        return $this->shared;
    } 

    public function getCreated() 
    {
        return $this->created;
    } 

    public function getData() 
    {
        return $this->data;
    } 

    public function setData($data) 
    {
        $this->data = $data;
    } 


    /**
     * Loads a draft based on a unique identifier
     *
     * @param string $key   The unique column identifying the draft. 
     *                      One of id/handle.
     * @param string $value The value to look for in the key column
     */
    private function load($value, $key='id') 
    {
        $db = $this->container->get('db');
        $sql = "SELECT * from `drafts` WHERE `$key` =".$db->quote($value);
        
        $result = $db->query($sql)->fetch(\PDO::FETCH_OBJ);

        if(!$result) return false;
        else foreach($result as $key => $val) {
            if($key == 'data' && $val != '') $this->$key = json_decode($val);
            else $this->$key = $val;
        }
    }
   
    /**
     * Loads a draft based on their id
     *
     * @param int $id
     */
    public function loadFromId($id) 
    {
        return $this->load($id, 'id');
    }
   
    /**
     * Loads a draft based on their handle
     *
     * @param string $handle
     */
    public function loadFromHandle($handle) 
    {
        return $this->load($handle, 'handle');
    }
   
    /**
     * Creates a new draft and stores it in the database
     *
     * @param array $in The pattern options
     * @param Model $model The model object     
     * @param User $user The user object     
     * 
     * @return int The id of the newly created model
     */
    public function create($in, $user, $model) 
    {
        $data = json_encode($in, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            
        // Getting draft from core
        $in['service'] = 'draft';
        $this->setSvg($this->getDraft($in));
        $in['service'] = 'compare';
        $in['theme'] = 'Compare';
        $this->setCompared($this->getDraft($in));
        
        // Set basic info    
        $this->setUser($user->getId());
        $this->setModel($model->getId());
        $this->setPattern($in['pattern']);
        
        // Get the HandleKit to create the handle
        $handleKit = $this->container->get('HandleKit');
        $this->setHandle($handleKit->create('draft'));

        // Store in database
        $db = $this->container->get('db');
        $sql = "INSERT into `drafts`(
            `user`,
            `pattern`,
            `model`,
            `handle`,
            `data`,
            `svg`,
            `compared`,
            `notes`,
            `created`
             ) VALUES (
            ".$db->quote($this->getUser()).",
            ".$db->quote($this->getPattern()).",
            ".$db->quote($this->getModel()).",
            ".$db->quote($this->getHandle()).",
            ".$db->quote($data).",
            ".$db->quote($this->getSvg()).",
            ".$db->quote($this->getCompared()).",
            ".$db->quote($this->container['settings']['app']['motd']).",
            NOW()
            );";
        $db->exec($sql);

        // Retrieve draft ID
        $id = $db->lastInsertId();
        
        // Set draft name to 'pattern for model'
        $sql = "UPDATE `drafts` SET `name` = ".$db->quote('Draft '.$this->getHandle())." WHERE `drafts`.`id` = '$id';";
        $db->exec($sql);

        // Update instance from database
        $this->loadFromId($id);
    }

    private function getDraft($args)
    {
        $url = $this->container['settings']['app']['core_api']."/index.php";
        
        $config = [
            'connect_timeout' => 5,
            'timeout' => 35,
            'query' => $args,
            'debug' => $log,
        ];
        $guzzle = new GuzzleClient($config);
        $response = $guzzle->request('GET', $url);
        
        return $response->getBody();
    }

    /** Saves the model to the database */
    public function save() 
    {
        $db = $this->container->get('db');
        $sql = "UPDATE `models` set 
            `user`    = ".$db->quote($this->getUser()).",
            `name` = ".$db->quote($this->getName()).",
            `body`   = ".$db->quote($this->getBody()).",
            `picture`  = ".$db->quote($this->getPicture()).",
            `data`     = ".$db->quote(json_encode($this->data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)).",
            `units`     = ".$db->quote($this->units).",
            `migrated`     = ".$db->quote($this->migrated).",
            `shared`   = ".$db->quote($this->getShared()).",
            `notes`     = ".$db->quote($this->notes)."
            WHERE 
            `id`       = ".$db->quote($this->getId()).";";

        return $db->exec($sql);
    }
    
    /**
     * Loads all drafts for a model
     *
     * @param int $id
     *
     * @return array|false An array of drafts or false
     */
    public function getDrafts() 
    {
        $db = $this->container->get('db');
        $sql = "SELECT * from `drafts` WHERE `model` =".$db->quote($this->getId());
        $result = $db->query($sql)->fetchAll(\PDO::FETCH_OBJ);
        
        if(!$result) return false;
        else {
            foreach($result as $key => $val) {
                $drafts[$val->id] = $val;
            }
        } 
        return $drafts;
    }

    /** Remove a model */
    public function remove($user) 
    {
        // Remove from storage
        shell_exec("rm -rf ".$this->container['settings']['storage']['static_path']."/users/".substr($user->getHandle(),0,1).'/'.$user->getHandle().'/models/'.$this->getHandle());
        
        // Remove from database
        $db = $this->container->get('db');
        $sql = "DELETE from `models` WHERE `id` = ".$db->quote($this->getId()).";";

        return $db->exec($sql);
    }
    
}