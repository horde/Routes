<?php
/**
 * Helper class to generate the match dictionary for the incoming request.
 *
 * PHP version 5
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

/**
 * Generates the match dictionary for the incoming request.
 *
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you did not
 * receive this file, see
 * http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class Horde_Routes_Matcher
{
    /**
     * The routes mapper.
     *
     * @var Horde_Routes_Mapper
     */
    protected $_mapper;

    /**
     * The incoming request.
     *
     * @var Horde_Controller_Request
     */
    protected $_request;

    /**
     * The match dictionary.
     *
     * @var array
     */
    protected $_match_dict;

    /**
     * Constructor
     *
     * @param Horde_Routes_Mapper $mapper        The mapper
     * @param Object $request  A request object that implements a ::getPath()
     *                         method similar to Horde_Controller_Request::
     */
    public function __construct(
        Horde_Routes_Mapper $mapper,
        $request)
    {
        $this->_mapper = $mapper;
        $this->_request = $request;
    }

    /**
     * Return the match dictionary for the incoming request.
     *
     * @return array The match dictionary.
     */
    public function getMatchDict()
    {
        if ($this->_match_dict === null) {
            // Auto-populate environ from request
            if (method_exists($this->_request, 'getMethod')) {
                $this->_mapper->environ = array('REQUEST_METHOD' => $this->_request->getMethod());
            }

            // Extract path from request - handle both PSR-7 and Horde_Controller_Request
            if (method_exists($this->_request, 'getPath')) {
                // Horde_Controller_Request
                $path = $this->_request->getPath();
            } elseif (method_exists($this->_request, 'getUri')) {
                // PSR-7 ServerRequestInterface
                $path = $this->_request->getUri()->getPath();
            } else {
                throw new RuntimeException('Request must implement getPath() or getUri()');
            }

            // Strip query string
            if (($pos = strpos($path, '?')) !== false) {
                $path = substr($path, 0, $pos);
            }
            if (!$path) {
                $path = '/';
            }
            $this->_match_dict = new Horde_Support_Array($this->_mapper->match($path));
        }
        return $this->_match_dict;
    }

}
