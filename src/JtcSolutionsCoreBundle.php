<?php declare(strict_types = 1);

namespace JtcSolutions\Core;

use JtcSolutions\Core\DependencyInjection\JtcSolutionsCoreExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class JtcSolutionsCoreBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new JtcSolutionsCoreExtension();
        }

        return $this->extension;
    }
}
