<?php
/**
 * Joomla! System plugin - Auto Login
 *
 * @author     Spletodrom <info@spletodrom.com>
 * @copyright  Copyright 2016 Spletodrom.com. All rights reserved
 * @license    GNU Public License
 * @link       https://www.spletodrom.com
 */

// No direct access
defined('_JEXEC') or die;

// Import parent library
jimport('joomla.plugin.plugin');

/**
 * Auto Login System Plugin
 */
class PlgSystemAutoLogin extends JPlugin
{
	/**
	 * @var JApplicationCms
	 */
	protected $app;

	/**
	 * @var JInput
	 */
	protected $input;

	/**
	 * @var int
	 */
	protected $userId = 0;

	/**
	 * Method to initialize a bunch of stuff for this plugin
	 */
	public function init()
	{
		$this->app = JFactory::getApplication();
		$this->input = $this->app->input;
	}

	/**
	 * Catch the event onAfterInitialise
	 *
	 * @return void
	 */
	public function onAfterRoute()
	{
		$this->init();

		// Check if this plugin is allowed to run
		if ($this->allowLogin() === false)
		{
			return;
		}

		// Load the user
		$user = $this->loadUser();

		if ($user === false)
		{
			return;
		}

		// Login the user
		$this->doLogin($user);
	}

	/**
	 * @return boolean|JUser
	 */
	protected function loadUser()
	{
		// Initialize the user-ID
		$this->userId = trim($this->params->get('userid'));

		// Check for an userid
		if (empty($this->userId) && $this->userId < 1)
		{
			return false;
		}

		// Load the user
		$user = JFactory::getUser();
		$user->load($this->userId);

		if (!$user->id > 0 || !$user instanceof JUser)
		{
			return false;
		}

		return $user;
	}

	/**
	 * Helper-method to get the redirect URL for this login procedure
	 *
	 * @return string
	 */
	protected function getRedirectUrl()
	{
		$redirect = $this->params->get('redirect');

		if ($redirect > 0)
		{
			$redirect = JRoute::_('index.php?Itemid=' . $redirect);
		}
		else
		{
			$redirect = '';
		}

		return $redirect;
	}

	/**
	 * Helper-method to login a specific user
	 *
	 * @param JUser $user
	 *
	 * @return boolean
	 */
	protected function doLogin(JUser $user)
	{
		// Allow a page to redirect the user to
		$redirect = $this->getRedirectUrl();

		// Construct the options
		$options = array();
		$options['remember'] = true;
		$options['return'] = $redirect;
		$options['action'] = 'core.login.site';

		// Construct a response
		jimport('joomla.user.authentication');
		JPluginHelper::importPlugin('authentication');
		JPluginHelper::importPlugin('user');
		$authenticate = JAuthentication::getInstance();

		// Construct the response-object
		$response = new JAuthenticationResponse;
		$response->type = 'Joomla';
		$response->email = $user->email;
		$response->fullname = $user->name;
		$response->username = $user->username;
		$response->password = $user->username;
		$response->language = $user->getParam('language');
		$response->status = JAuthentication::STATUS_SUCCESS;
		$response->error_message = null;

		// Authorise this response
		$authenticate->authorise($response, $options);

		// Run the login-event
		$this->app->triggerEvent('onUserLogin', array((array) $response, $options));

		// Set a cookie so that we don't do this twice
		if ($this->params->get('cookie') == 1)
		{
			$cookie = $this->app->input->cookie;
			$cookie->set('autologin', 1, 0);
		}

		// Redirect if needed
		if (!empty($redirect))
		{
			$this->app->redirect($redirect);
		}
	}

	/**
	 * Helper-method to determine whether a login is allowed or not
	 *
	 * @return boolean
	 */
	protected function allowLogin()
	{
		// Load system variables
		$user = JFactory::getUser();

		// Only allow usage from within the right app
		$allowedApp = $this->params->get('application', 'site');

		if ($allowedApp == 'admin')
		{
			$allowedApp = 'administrator';
		}

		if ($this->app->getName() !== $allowedApp && !in_array($allowedApp, array('both', 'all')))
		{
			return false;
		}

		// Skip AJAX requests
		if ($this->isAjaxRequest())
		{
			return false;
		}

		// If the current user is not a guest, authentication has already occurred
		if ((bool) $user->guest == false)
		{
			return false;
		}

		// Check for the cookie
		if ($this->params->get('cookie') == 1 && $this->app->input->cookie->get('autologin') == 1)
		{
			return false;
		}

		return true;
	}

	/**
	 * Check whether the current request is an AJAX or AHAH request
	 *
	 * @return bool
	 */
	protected function isAjaxRequest()
	{
		if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
		{
			return true;
		}

		$format = $this->input->getCmd('format');
		$tmpl = $this->input->getCmd('tmpl');
		$type = $this->input->getCmd('type');

		if (in_array($format, array('raw', 'feed')) || in_array($type, array('rss', 'atom')) || $tmpl === 'component')
		{
			return true;
		}

		return false;
	}
}
