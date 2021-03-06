<?php

/*
 * This file is part of the FOSElasticaBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FOS\ElasticaBundle\Tests\Functional;

use FOS\ElasticaBundle\DataCollector\ElasticaDataCollector;
use FOS\ElasticaBundle\Logger\ElasticaLogger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Extension\CodeExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @group functional
 */
class ProfilerTest extends WebTestCase
{
    /** @var ElasticaLogger */
    private $logger;

    /** @var \Twig_Environment */
    private $twig;

    /** @var ElasticaDataCollector */
    private $collector;

    public function setUp()
    {
        $this->logger = new ElasticaLogger($this->createMock(LoggerInterface::class), true);
        $this->collector = new ElasticaDataCollector($this->logger);

        $twigLoaderFilesystem = new \Twig_Loader_Filesystem(__DIR__ . '/../../src/Resources/views/Collector');
        $twigLoaderFilesystem->addPath(__DIR__ . '/../../vendor/symfony/web-profiler-bundle/Resources/views', 'WebProfiler');
        $this->twig = new \Twig_Environment($twigLoaderFilesystem, ['debug' => true, 'strict_variables' => true]);

        $this->twig->addExtension(new CodeExtension('', '', ''));
        $this->twig->addExtension(new RoutingExtension($this->getMockBuilder(UrlGeneratorInterface::class)->getMock()));
        $this->twig->addExtension(new HttpKernelExtension($this->getMockBuilder(FragmentHandler::class)->disableOriginalConstructor()->getMock()));

        $loader = $this->getMockBuilder(\Twig_RuntimeLoaderInterface::class)->getMock();
        $loader->method('load')->willReturn($this->getMockBuilder(HttpKernelRuntime::class)->disableOriginalConstructor()->getMock());
        $this->twig->addRuntimeLoader($loader);
    }

    public function testRender()
    {
        $connection = [
            'host' => 'localhost',
            'port' => '9200',
            'transport' => 'http',
        ];
        $this->logger->logQuery('index/_search', 'GET', json_decode('{"query":{"match_all":{}}}'), 1, $connection);
        $this->collector->collect($request = new Request(), new Response());

        $output = $this->twig->render('elastica.html.twig', [
            'request' => $request,
            'collector' => $this->collector,
            'queries' => $this->logger->getQueries(),
        ]);

        $output = str_replace("&quot;", '"', $output);

        $this->assertContains('{"query":{"match_all":{}}}', $output);
        $this->assertContains('index/_search', $output);
        $this->assertContains('localhost:9200', $output);
    }
}
