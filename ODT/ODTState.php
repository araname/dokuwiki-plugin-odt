<?php

require_once DOKU_PLUGIN.'odt/ODT/elements/ODTStateElement.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTRoot.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementSpan.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementParagraph.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementList.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementListItem.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementTable.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementTableColumn.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementTableRow.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementTableCell.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementTableHeaderCell.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementFrame.php';
require_once DOKU_PLUGIN.'odt/ODT/elements/ODTElementTextBox.php';

/**
 * ODTState: class for maintaining the ODT state stack.
 *
 * In general this is a setter/getter class for ODT states.
 * The intention is to get rid of some global state variables.
 * Especially the global error-prone $in_paragraph which easily causes
 * a document to become invalid if once set wrong. Now each state/element
 * can set their own instance of $in_paragraph which hopefully makes it use
 * a bit safer. E.g. for a new table-cell or list-item it can be set to false
 * because they allow creation of a new paragraph. On leave() we throw the
 * current state variables away and are safe back from where we came from.
 * So we also don't need to worry about correct re-initialization of global
 * variables anymore.
 * 
 * @license GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author  LarsDW223
 */
class ODTState
{
    protected $stack = array();
    protected $size = 0;
    protected $element_counter = array();

    /**
     * Constructor. Set initial 'root' state.
     */
    public function __construct() {
        $this->stack [$this->size] = new ODTElementRoot();
        $this->size++;
    }

    /**
     * Get current list item.
     * If the function returns NULL then that means that we are
     * currently not in a list item.
     *
     * @return ODTStateElement|NULL
     */
    public function getCurrentListItem() {
        return $this->findClosestWithClass ('list-item');
    }

    /**
     * Get current frame.
     * If the function returns NULL then that means that we are
     * currently not in a frame.
     *
     * @return ODTStateElement|NULL
     */
    public function getCurrentFrame() {
        return $this->findClosestWithClass ('frame');
    }

    /**
     * Get current list.
     * If the function returns NULL then that means that we are
     * currently not in a list.
     * 
     * @return ODTStateElement|NULL
     */
    public function getCurrentList() {
        return $this->findClosestWithClass ('list');
    }

    /**
     * Get current paragraph.
     * If the function returns NULL then that means that we are
     * currently not in a paragraph.
     * 
     * @return ODTStateElement|NULL
     */
    public function getCurrentParagraph() {
        return $this->findClosestWithClass ('paragraph');
    }

    /**
     * Get current table.
     * If the function returns NULL then that means that we are
     * currently not in a table.
     * 
     * @return ODTStateElement|NULL
     */
    public function getCurrentTable() {
        return $this->findClosestWithClass ('table');
    }

    /**
     * Enter a new state with element name $element and class $clazz.
     * E.g. 'text:p' and 'paragraph'.
     * 
     * @param string $element
     * @param string $clazz
     */
    public function enter(ODTStateElement $element) {
        if ($element == NULL ) {
            return;
        }
        $name = $element->getElementName();

        // Increase the counter for that element
        if ($this->element_counter [$name] == NULL ) {
            $this->element_counter [$name] = 1;
        } else {
            $this->element_counter [$name]++;
        }
        $element->setCount($this->element_counter [$name]);

        // Get the current element
        $previous = $this->stack [$this->size-1];

        // Add new element to stack
        $this->stack [$this->size] = $element;
        $this->size++;

        // Let the element find its parent
        $element->determineParent ($previous);
    }

    /**
     * Get current element on top of the stack.
     * 
     * @return ODTStateElement
     */
    public function getCurrent() {
        return $this->stack [$this->size-1];
    }

    /**
     * Leave current state. All data of the curent state is thrown away.
     */
    public function leave() {
        // We always will keep the initial state.
        // That means we do nothing if size is 0. This would be a fault anyway.
        if ($this->size > 1) {
            unset ($this->stack [$this->size-1]);
            $this->size--;
        }
    }

    /**
     * Reset the state stack/go back to the initial state.
     * All states except the root state will be discarded.
     */
    public function reset() {
        // Throw away any states except the initial state.
        // Reset size to 1.
        for ($reset = 1 ; $reset < $this->size ; $reset++) {
            unset ($this->stack [$reset]);
        }
        $this->size = 1;
    }

    /**
     * Find the closest state with class $clazz.
     *
     * @param string $clazz
     * @return ODTStateEntry|NULL
     */
    public function findClosestWithClass($clazz) {
        for ($search = $this->size-1 ; $search > 0 ; $search--) {
            if ($this->stack [$search]->getClass() == $clazz) {
                return $this->stack [$search];
            }
        }
        // Nothing found.
        return NULL;
    }

    /**
     * toString() function. Only for creating debug dumps.
     * 
     * @return string
     */
    public function toString () {
        $indent = '';
        $string = 'Stackdump:';
        for ($search = 0 ; $search < $this->size ; $search++) {
            $string .= $indent . $this->stack [$search]->getElementName().';';
            $indent .= '    ';
        }
        return $string;
    }

    /**
     * Find the closest state with class $clazz.
     *
     * @param string $clazz
     * @return ODTStateEntry|NULL
     */
    public function countClass($clazz) {
        $count = 0;
        for ($search = $this->size-1 ; $search > 0 ; $search--) {
            if ($this->stack [$search]->getClass() == $clazz) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Find the closest element with element name $name.
     *
     * @param string $name
     * @return ODTStateElement|NULL
     */
    public function findClosestWithName($name) {
        for ($search = $this->size-1 ; $search > 0 ; $search--) {
            if ($this->stack [$search]->getElementName() == $name) {
                return $this->stack [$search];
            }
        }
        // Nothing found.
        return NULL;
    }
    
    /**
     * Are we in a paragraph?
     * 
     * @return bool
     */
    public function getInParagraph() {
        // Ask the current element
        if ($this->size > 0) {
            return $this->stack [$this->size-1]->getInParagraph();
        } else {
            return false;
        }
    }
}
