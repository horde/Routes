<?php
/**
 * Horde Routes package
 *
 * This package is heavily inspired by the Python "Routes" library
 * by Ben Bangert (http://routes.groovie.org).  Routes is based
 * largely on ideas from Ruby on Rails (http://www.rubyonrails.org).
 *
 * @author  Maintainable Software, LLC. (http://www.maintainable.com)
 * @author  Mike Naberezny <mike@maintainable.com>
 * @license http://www.horde.org/licenses/bsd BSD
 * @package Routes
 */

namespace Horde\Routes;

use Horde_Exception_Wrapped;

/**
 * Exception class for the Horde\Routes package.  All exceptions thrown
 * from the package will be of this type.
 *
 * @package Routes
 */
class Exception extends Horde_Exception_Wrapped
{
}
