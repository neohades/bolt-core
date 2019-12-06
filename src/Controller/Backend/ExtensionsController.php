<?php

declare(strict_types=1);

namespace Bolt\Controller\Backend;

use Bolt\Extension\ExtensionRegistry;
use ComposerPackages\Dependencies;
use ComposerPackages\Versions;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class ExtensionsController extends AbstractController implements BackendZone
{
    /** @var ExtensionRegistry */
    private $extensionRegistry;

    /**
     * @var Dependencies
     */
    private $dependenciesManager;

    public function __construct(ExtensionRegistry $extensionRegistry)
    {
        $this->extensionRegistry = $extensionRegistry;
        $this->dependenciesManager = new Dependencies();
    }

    /**
     * @Route("/extensions", name="bolt_extensions")
     */
    public function index(): Response
    {
        $extensions = $this->extensionRegistry->getExtensions();

        $twigvars = [
            'extensions' => $extensions,
        ];

        return $this->render('@bolt/pages/extensions.html.twig', $twigvars);
    }

    /**
     * @Route("/extensions/{name}", name="bolt_extensions_view", requirements={"name"=".+"})
     */
    public function viewExtension($name): Response
    {
        $name = str_replace('/', '\\', $name);
        $extension = $this->extensionRegistry->getExtension($name);
        $dependencies = iterator_to_array($this->dependenciesManager->get($extension->getComposerPackage()->getName()));
        $extension->dependencies = [];

        foreach ($dependencies as $dependency) {
            $extDependency['name'] = $dependency;
            $extDependency['version'] = Versions::get($dependency);
            $extension->dependencies[] = $extDependency;
        }

        $twigvars = [
            'extension' => $extension,
        ];

        return $this->render('@bolt/pages/extension_details.html.twig', $twigvars);
    }
}
