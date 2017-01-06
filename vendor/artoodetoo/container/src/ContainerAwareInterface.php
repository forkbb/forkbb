<?php

namespace R2\DependencyInjection;

interface ContainerAwareInterface
{
    /**
     * Sets the Container.
     * Provides a fluent interface.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     *
     * @return $this
     */
    public function setContainer(ContainerInterface $container);
}
