<?php

namespace Drupal\paytabs_drupal_commerce\Session;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Session\SessionConfigurationInterface;


class CookieSameSiteSessionConfiguration implements SessionConfigurationInterface
{

    protected $options;

    public function __construct(array $options = []) {
        // Set default options if none are provided.
        $this->options = array_merge($this->getDefaultOptions(), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getOptions(Request $request)
    {
      $options = [
              'cookie_lifetime' => 0,   // Define any other options needed.
              'cookie_secure' => true,  // Example setting
              // Add other default options here.
          ];

          // Set the cookie SameSite option to None.
          $options['cookie_samesite'] = 'None';

          return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options) {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getOption($name) {
        return $this->hasOption($name) ? $this->options[$name] : NULL;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOption($name) {
        return array_key_exists($name, $this->options);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieSecure() {
        return isset($this->options['cookie_secure']) ? $this->options['cookie_secure'] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieSameSite() {
        return isset($this->options['cookie_samesite']) ? $this->options['cookie_samesite'] : 'Lax';
    }

    /**
     * {@inheritdoc}
     */
    public function hasSession(Request $request) {
        // Get the session service.
        try {
          $session = \Drupal::service('session');

          // Check if the session is initialized.
          return $session->isStarted() && $session->getId() !== '';
        } catch (\Exception $e) {
          // Log the exception for debugging.
          \Drupal::logger('paytabs_drupal_commerce')->error($e->getMessage());
          return false; // Return false if an exception occurs.
        }
  }

    /**
     * Get default options for session configuration.
     */
    protected function getDefaultOptions() {
        return [
            'cookie_samesite' => 'None',  // Default SameSite setting
            'cookie_secure' => true,       // Secure cookies if using HTTPS
            // Add other default session options as needed.
        ];
    }

}
