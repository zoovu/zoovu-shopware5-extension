<?php
namespace semknoxSearch\Subscriber;
use Enlight\Event\SubscriberInterface;
use Enlight_Controller_ActionEventArgs as ControllerActionEventArgs;
class Backend implements SubscriberInterface
{
    /**
     */
    public function __construct()
    {
    }
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatchSecure_Backend_Index' => 'onPostDispatchBackend',
        ];
    }
    public function onPostDispatchBackend(\Enlight_Controller_ActionEventArgs $args)
    {
        $view = $args->getSubject()->View();
        $view->extendsTemplate(__DIR__ . '/../Resources/views/backend/semknox_search_backend_module/index/semknox_header.tpl');
    }
    /**
     * @param ControllerActionEventArgs $args
     */
    public function onPostDispatchBackendIndex(ControllerActionEventArgs $args)
    {
        $request = $args->getRequest();
        $view = $args->getSubject()->View();
        if ($request->getActionName() === 'index') {
            $view->extendsTemplate('backend/index/semknox_header.tpl');
        }
    }
}
