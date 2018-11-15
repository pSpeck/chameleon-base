<?php

/*
 * This file is part of the Chameleon System (https://www.chameleonsystem.com).
 *
 * (c) ESONO AG (https://www.esono.de)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ChameleonSystem\CoreBundle\EventListener;

use ChameleonSystem\CoreBundle\Maintenance\MaintenanceMode\MaintenanceModeServiceInterface;
use ChameleonSystem\CoreBundle\Service\Initializer\RequestInitializer;
use ChameleonSystem\CoreBundle\Service\RequestInfoServiceInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

class InitializeRequestListener
{
    /**
     * @var RequestInitializer
     */
    private $requestInitializer;

    /**
     * @var MaintenanceModeServiceInterface
     */
    private $maintenanceModeService;

    /**
     * @var RequestInfoServiceInterface
     */
    private $requestInfoService;

    public function __construct(
        RequestInitializer $requestInitializer,
        MaintenanceModeServiceInterface $maintenanceModeService,
        RequestInfoServiceInterface $requestInfoService
    ) {
        $this->requestInitializer = $requestInitializer;
        $this->maintenanceModeService = $maintenanceModeService;
        $this->requestInfoService = $requestInfoService;
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if (false === $this->requestInfoService->isBackendMode() && false === $this->requestInfoService->isCmsTemplateEngineEditMode()) {
            $this->recheckMaintenanceMode($event);
        }

        $this->requestInitializer->initialize($event->getRequest());
    }

    private function recheckMaintenanceMode(GetResponseEvent $event): void
    {
        if (true === $this->maintenanceModeService->isActive()) {
            $this->showMaintenanceModePage();
        }
    }

    // TODO not used: see #119 - this probably needs a different solution.
    private function redirectToCurrentPage(GetResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (true === $request->isMethodSafe()) {
            $event->setResponse(new RedirectResponse($_SERVER['REQUEST_URI']));
        } else {
            // Redirect is not meaningful for a POST request:
            $event->setResponse(new Response('Maintenance mode is active.', Response::HTTP_SERVICE_UNAVAILABLE));
        }
    }

    private function showMaintenanceModePage(): void
    {
        if (\file_exists(PATH_WEB.'/maintenance.php')) {
            require PATH_WEB.'/maintenance.php';

            exit();
        }

        die('Sorry! This page is down for maintenance.');
    }
}
