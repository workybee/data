<?php
/** App\Data\Model class */
namespace App\Data;

use Symfony\Component\Yaml\Yaml;

/**
 * The model class.
 *
 * @author Joost De Cock <joost@decock.org>
 * @copyright 2017 Joost De Cock
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, Version 3
 */
class Model
{
    /** @var \Slim\Container $container The container instance */
    protected $container;

    /** @var int $id Unique id of the model */
    private $id;

    /** @var int $user ID of the user owning the model */
    private $user;

    /** @var string $name Name of the model */
    private $name;

    /** @var string $handle Unique handle of the model */
    private $handle;

    /** @var string $body The body type of the model. One of female/male/other 
     * Note: This is not about gender, but about curves
     */
    private $body;

    /** @var string $picture File name of the user's avatar */
    private $picture;

    /** @var string $data Other app data stored as JSON */
    private $data;

    /** @var string $created The time the model was created */
    private $created;

    /** @var bool $migrated Whether the model was migrated (from MMP) */
    private $migrated;

    /** @var bool $shared Whether the model is shared */
    private $shared;

    /** @var string $notes the model notes */
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

    public function setNotes($notes) 
    {
        $this->notes = $notes;
        return true;
    } 

    public function getNotes() 
    {
        return $this->notes;
    } 

    public function setBody($body) 
    {
        $this->body = $body;
        return true;
    } 

    public function getBody() 
    {
        return $this->body;
    } 

    public function setMigrated($migrated) 
    {
        $this->migrated = $migrated;
        return true;
    } 

    public function getMigrated() 
    {
        return $this->migrated;
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

    public function setPicture($picture) 
    {
        $this->picture = $picture;
        return true;
    } 

    public function getPicture() 
    {
        return $this->picture;
    } 

    public function setUnits($units) 
    {
        if($units === 'metric' || $units === 'imperial') $this->units = $units;
        else return false;

        return true;
    } 

    public function getUnits() 
    {
        return $this->units;
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
     * Loads a model based on a unique identifier
     *
     * @param string $key   The unique column identifying the user. 
     *                      One of id/handle.
     * @param string $value The value to look for in the key column
     *
     * @return object|false A model object or false if model does not exist
     */
    private function load($value, $key='id') 
    {
        $db = $this->container->get('db');
        $sql = "SELECT * from `models` WHERE `$key` =".$db->quote($value);
        
        $result = $db->query($sql)->fetch(\PDO::FETCH_OBJ);

        if(!$result) return false;
        else foreach($result as $key => $val) {
            if($key == 'data' && $val != '') $this->$key = json_decode($val);
            else $this->$key = $val;
            }
    }
   
    /**
     * Loads a model based on their id
     *
     * @param int $id
     *
     * @return object|false A model object or false if user does not exist
     */
    public function loadFromId($id) 
    {
        return $this->load($id, 'id');
    }
   
    /**
     * Loads a model based on their handle
     *
     * @param string $handle
     *
     * @return object|false A model object or false if user does not exist
     */
    public function loadFromHandle($handle) 
    {
        return $this->load($handle, 'handle');
    }
   
    /**
     * Creates a new model and stores it in database
     *
     * @param User $user The user object     
     * 
     * @return int The id of the newly created model
     */
    public function create($user) 
    {
        // Set basic info    
        $this->setUser($user->getId());
        
        // Get the HandleKit to create the handle
        $handleKit = $this->container->get('HandleKit');
        $this->setHandle($handleKit->create('model'));

        // Get the AvatarKit to create the avatar
        $avatarKit = $this->container->get('AvatarKit');
        $this->setPicture($avatarKit->create($user->getHandle(), 'model', $this->getHandle()));
        
        // Store in database
        $db = $this->container->get('db');
        $sql = "INSERT into `models`(
            `user`,
            `handle`,
            `picture`,
            `created`
             ) VALUES (
            ".$db->quote($this->getUser()).",
            ".$db->quote($this->getHandle()).",
            ".$db->quote($this->getPicture()).",
            NOW()
            );";
        $db->exec($sql);

        // Retrieve model ID
        $id = $db->lastInsertId();
        
        // Set modelname to #ID to encourage people to change it
        $sql = "UPDATE `models` SET `name` = '#$id' WHERE `models`.`id` = '$id';";
        $db->exec($sql);

        // Update instance from database
        $this->loadFromId($id);
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

    /**
     * Exports model data to disk (for download by user)
     *
     * @return string name of the directory where the data is stored
     */
    public function export() 
    {
        // Units
        if($this->getUnits() == 'imperial') $units = 'inch';
        else $units = 'cm';

        // Load measurements
        $measurements = (array)$this->getData()->measurements;
        ksort($measurements);

        // Create random directory
        $token = sha1(print_r($this,1).time());
        $dir = $this->container['settings']['storage']['static_path']."/export/$token";
        mkdir($dir);

        // Export as CSV
        $fp = fopen("$dir/".$this->getHandle().'.csv', 'w');
        fputcsv($fp, ['Measurement', 'value', 'units']);
        foreach($measurements as $key => $val) fputcsv($fp, [$key, $val, $units]);
        fclose($fp);

        // Export as JSON
        $fp = fopen("$dir/".$this->getHandle().'.json', 'w');
        fwrite($fp, json_encode($measurements, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        fclose($fp);

        // Export as YAML
        $fp = fopen("$dir/".$this->getHandle().'.yaml', 'w');
        fwrite($fp, '# All measurements in '."$units\n".Yaml::dump($measurements));
        fclose($fp);

        return '/static/export/'.$token;
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
