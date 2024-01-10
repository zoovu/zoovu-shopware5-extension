<?php
namespace semknoxSearch\Subscriber;
use Enlight\Event\SubscriberInterface;
class Template implements SubscriberInterface
{
    /**
     * @var string
     */
    private $pluginDir;
    /**
     * @param string $pluginDir
     */
    public function __construct($pluginDir)
    {
        $this->pluginDir = $pluginDir;
    }
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch' => 'onActionPreDispatch',
        ];
    }
    /**
     * @param \Enlight_Event_EventArgs $args
     */
    public function onActionPreDispatch(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_View $view */
        $view = $args->get('subject')->View();
        $view->addTemplateDir($this->pluginDir . '/Resources/views');
    }
}
