<?php
/**
 * Helper class to generate the match dictionary for the incoming request.
 *
 * PHP version 7
 *
 * @category Horde
 * @package  Horde_Routes
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Routes;

use Horde_Controller_Request;
use Horde_Support_Array;
use Psr\Http\Message\ServerRequestInterface;

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
class Matcher
{
    /**
     * The routes mapper.
     *
     * @var Mapper
     */
    protected Mapper $mapper;

    /**
     * The incoming request.
     *
     * @var Horde_Controller_Request|ServerRequestInterface
     */
    protected $request;

    /**
     * The match dictionary.
     *
     * @var array|Horde_Support_Array
     */
    protected $match_dict;

    /**
     * Constructor
     *
     * @param Mapper $mapper        The mapper
     * @param Horde_Controller_Request|ServerRequestInterface $request  A request object that implements a ::getPath()
     *                         method similar to Horde_Controller_Request::
     */
    public function __construct(
        Mapper $mapper,
        $request
    ) {
        $this->mapper = $mapper;
        $this->request = $request;
    }

    /**
     * Return the match dictionary for the incoming request.
     *
     * @return array The match dictionary.
     */
    public function getMatchDict()
    {
        if ($this->match_dict === null) {
            $path = $this->request->getPath();
            if (($pos = strpos($path, '?')) !== false) {
                $path = substr($path, 0, $pos);
            }
            if (!$path) {
                $path = '/';
            }
            $this->match_dict = new Horde_Support_Array($this->mapper->match($path));
        }
        return $this->match_dict;
    }
}
