<?php

namespace Rest\Controllers;

use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Request;
use DataWarehouse\Query\Exceptions\BadRequestException;

use Models\Services\Acls;
use User\Roles;

class SummaryControllerProvider extends BaseControllerProvider
{
    /**
     * @see BaseControllerProvider::setupRoutes
     */
    public function setupRoutes(Application $app, ControllerCollection $controller)
    {
        $root = $this->prefix;
        $class = get_class($this);

        $controller->get("$root/portlets", "$class::getPortlets");

        $controller->post("$root/layout", "$class::setLayout");
        $controller->delete("$root/layout", "$class::resetLayout");
    }

    /*
     * Get the column layout manager for the user
     *
     * @return \CCR\ColumnLayout
     */
    private function getLayout($user)
    {
        $defaultLayout = null;
        $defaultColumnCount = 2;

        if ($user->isPublicUser() === false) {
            $layoutStore = new \UserStorage($user, 'summary_layout');
            $record = $layoutStore->getById(0);
            if ($record) {
                $defaultLayout = $record['layout'];
                $defaultColumnCount = $record['columns'];
            }
        }

        return new \CCR\ColumnLayout($defaultColumnCount, $defaultLayout);
    }

    /**
     */
    public function getPortlets(Request $request, Application $app)
    {
        $user = $this->getUserFromRequest($request);

        $summaryPortlets = array();

        $mostPrivilegedAcl = Acls::getMostPrivilegedAcl($user);


        $layout = $this->getLayout($user);

        $presetPortlets = array();
        try {
            $presetPortlets = Roles::getConfig($mostPrivilegedAcl->getName(), 'summary_portlets');
        } catch (\Exception $e){
        }

        foreach ($presetPortlets as $portlet) {
            list($chartLocation, $column) = $layout->getLocation('PP' . $portlet['name']);
            $summaryPortlets[$chartLocation] = array(
                'name' => 'PP' . $portlet['name'],
                'type' => $portlet['type'],
                'config' => $portlet['config'],
                'column' => $column
            );
        }

        $presetCharts = Roles::getConfig($mostPrivilegedAcl->getName(), 'summary_charts');

        foreach ($presetCharts as $index => $presetChart)
        {
            list($chartLocation, $column) = $layout->getLocation('PC' . $index);
            $summaryPortlets[$chartLocation] = array(
                'name' => 'PC' . $index,
                'type' => 'ChartPortlet',
                'config' => $presetChart,
                'column' => $column
            );
        }

        if ($user->isPublicUser() === false)
        {
            $queryStore = new \UserStorage($user, 'queries_store');
            $queries = $queryStore->get();

            if ($queries != null) {
                foreach ($queries as $query) {
                    if (!isset($query['config']) || !isset($query['name'])) {
                        continue;
                    }

                    $queryConfig = json_decode($query['config']);

                    if (!$queryConfig->featured) {
                        continue;
                    }

                    $name = 'UC' . $query['name'];

                    if (preg_match('/summary_(?P<index>\S+)/', $query['name'], $matches) > 0) {
                        if (isset($summaryPortlets[$matches['index']])) {
                            $name = 'PC' . $matches['index'];
                        }
                    }

                    list($chartLocation, $column) = $layout->getLocation($name);

                    $summaryPortlets[$chartLocation] = array(
                        'name' => $name,
                        'type' => 'ChartPortlet',
                        'config' => $queryConfig,
                        'column' => $column
                    );
                }
            }
        }

        ksort($summaryPortlets);

        return $app->json(array(
            'success' => true,
            'total' => count($summaryPortlets),
            'portalConfig' => array('columns' => $layout->getColumnCount(), 'layoutstyle' => 'portal'),
            'data' => array_values($summaryPortlets)
        ));
    }

    /**
     * set the layout metadata
     *
     */
    public function setLayout(Request $request, Application $app)
    {
        $user = $this->authorize($request);

        $content = json_decode($this->getStringParam($request, 'data', true), true);

        if ($content === null || !isset($content['layout']) || !isset($content['columns'])) {
            throw new BadRequestException('Invalid data parameter');
        }

        $storage = new \UserStorage($user, 'summary_layout');

        return $app->json(array(
            'success' => true,
            'total' => 1,
            'data' => $storage->upsert(0, $content)
        ));
    }

    /**
     * clear the layout metadata
     *
     */
    public function resetLayout(Request $request, Application $app)
    {
        $user = $this->authorize($request);

        $storage = new \UserStorage($user, 'summary_layout');

        $storage->del();

        return $app->json(array(
            'success' => true,
            'total' => 1
        ));
    }
}