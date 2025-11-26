<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\PrestaShop\Core\Grid;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\GridDefinitionFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\GridFilterFormFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\RouteParamFilter;

/**
 * Class GridFactory is responsible for creating final Grid instance.
 */
class GridWithRouteParamFactory implements GridFactoryInterface
{
    /**
     * @param GridDefinitionFactoryInterface $definitionFactory
     * @param GridDataFactoryInterface $dataFactory
     * @param GridFilterFormFactoryInterface $filterFormFactory
     * @param HookDispatcherInterface $hookDispatcher
     */
    public function __construct(
        protected readonly GridDefinitionFactoryInterface $definitionFactory,
        protected readonly GridDataFactoryInterface $dataFactory,
        protected readonly GridFilterFormFactoryInterface $filterFormFactory,
        protected readonly HookDispatcherInterface $hookDispatcher,
        protected readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function getGrid(SearchCriteriaInterface $searchCriteria): GridInterface
    {
        $definition = $this->definitionFactory->getDefinition();
        $filters = $definition->getFilters();

        $routeFilters = [];
        foreach($filters->all() as $filter) {
            if($filter instanceof RouteParamFilter && isset($filter->getTypeOptions()['data'])) {
                $routeFilters[$filter->getName()] = $filter->getTypeOptions()['data'];
            }
        }

        if(!empty($routeFilters)) {
            $searchCriteria->addFilter($routeFilters);
        }

        $data = $this->dataFactory->getData($searchCriteria);

        $this->hookDispatcher->dispatchWithParameters('action' . Container::camelize($definition->getId()) . 'GridDataModifier', [
            'data' => &$data,
        ]);

	$submitActionUrl = null;
        if(!is_null($definition->getRoute()) && !empty($routeFilters)) {
            $submitActionUrl = $this->urlGenerator->generate($definition->getRoute(), $routeFilters);
        }

        $filterForm = $this->filterFormFactory->create($definition, $submitActionUrl);
        $filterForm->setData($searchCriteria->getFilters());

        return new Grid(
            $definition,
            $data,
            $searchCriteria,
            $filterForm
        );
    }
}
