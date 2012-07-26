<?php
namespace wcf\page;
use wcf\system\cache\CacheHandler;
use wcf\system\event\EventHandler;
use wcf\system\exception\SystemException;
use wcf\util\ClassUtil;

/**
 * Provides functionality for cached lists.
 *
 * @author Jim Martens
 * @copyright 2011-2012 Jim Martens
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @package de.plugins-zum-selberbauen.cachedList
 * @subpackage page
 * @category Community Framework
 */
abstract class AbstractCachedListPage extends SortablePage {
    
    /**
     * Contains the fully qualified name of the CacheBuilder.
     * @var string
     */
    public $cacheBuilderClassName = '';
    
    /**
     * Contains the name of the cache.
     * @var string
     */
    public $cacheName = '';
    
    /**
     * Contains the index of the returned cache data.
     * @var string
     */
    public $cacheIndex = '';
    
    /**
     * Contains all read objects.
     * @var array
     */
    public $objects = array();
    
    /**
     * Contains the current objects.
     * @var array
     */
    public $currentObjects = array();
    
    /**
     * @see \wcf\page\SortablePage::readData()
     */
    public function readData() {
        // calling SortablePage methods
        $this->validateSortOrder();
        $this->validateSortField();
        
        AbstractPage::readData();
        
        // calling MultipleLinkPage methods
        $this->initObjectList();
        $this->calculateNumberOfPages();
        
        // calling own methods
        $this->loadCache();
        
        // only read objects from database, when another sortField is chosen
        if ($this->items) {
            
            if ($this->sortField != $this->defaultSortField) {
                $this->sqlLimit = $this->itemsPerPage;
                $this->sqlOffset = ($this->pageNo - 1) * $this->itemsPerPage;
                if ($this->sortField && $this->sortOrder) $this->sqlOrderBy = $this->sortField." ".$this->sortOrder;
                
                $this->readObjects();
                $this->objectList->readObjectIDs();
                $objectIDs = $this->objectList->getObjectIDs();
                $objects = $this->objectList->getObjects();
                $this->objects = array_combine($objectIDs, $objects);
            } elseif ($this->sortOrder != $this->defaultSortOrder) {
                // if the default sortField is selected but another order is chosen
                // it's enough to reverse the already read array
                $this->objects = array_reverse($this->objects, true);
                $this->currentObjects = array_reverse($this->currentObjects, true);
            }
        }
        
    }
    
    /**
     * Loads the cache for the list.
     * To use a custom path please overwrite this method <br />and replace WCF_DIR with the wanted application dir.
     *
     * @param string $path the application path; default WCF_DIR
     */
    public function loadCache($path = WCF_DIR) {
        // call loadCache event
        EventHandler::getInstance()->fireEvent($this, 'loadCache');
        
        if (!ClassUtil::isInstanceOf($this->cacheBuilderClassName, 'wcf\system\cache\builder\ICacheBuilder')) {
            throw new SystemException("Class '".$this->cacheBuilderClassName."' does not implement 'wcf\system\cache\builder\ICacheBuilder'");
        }
        
        $file = $path.'cache/cache.'.$this->cacheName.'.php';
        CacheHandler::getInstance()->addResource(
            $this->cache,
            $file,
            $cacheBuilderClassName
        );
        $this->objects = CacheHandler::getInstance()->get($cache, $this->cacheIndex);
        $this->currentObjects = array_slice($this->objects, ($this->pageNo - 1) * $this->itemsPerPage, $this->itemsPerPage, true);
    }
    
    /**
     * <p>If your CacheBuilder returns another structure than one which contains directly the objects,<br />you should overwrite this method.</p>
     *
     * @see \wcf\page\MultipleLinkPage::countItems()
     */
    public function countItems() {
        // call countItems event
		EventHandler::getInstance()->fireAction($this, 'countItems');
		
        return count($this->objects);
    }
    
    /**
     * <p>If your CacheBuilder returns another structure than one which contains directly the objects,<br />you should overwrite this method.</p>
     *
     * @see \wcf\page\SortablePage::assignVariables()
     */
    public function assignVariables() {
        parent::assignVariables();
        
        // overwrite MultipleLinkPage objects assignment
		WCF::getTPL()->assign(array(
			'sortField' => $this->sortField,
			'sortOrder' => $this->sortOrder,
			'objects' => $this->currentObjects
		));
    }
}