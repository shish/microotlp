<?php

use OpenTelemetry\API\Globals;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Monolog\Logger;
use OpenTelemetry\Contrib\Logs\Monolog\Handler;
use Psr\Log\LogLevel;

require __DIR__ . '/vendor/autoload.php';

$tracer = Globals::tracerProvider()->getTracer('demo');
$loggerProvider = Globals::loggerProvider();
$handler = new Handler(
    $loggerProvider,
    LogLevel::INFO
);
$monolog = new Logger('otel-php-monolog', [$handler]);

$app = AppFactory::create();

$app->get('/rolldice-bare', function (Request $request, Response $response) {
    $startTime = hrtime(true);
    $total = 0;
    for ($i = 0; $i < 10_000; $i++) {
        $total += random_int(1, 6);
    }

    $durationMs = sprintf("%7.2f", (hrtime(true) - $startTime) / 1e6);

    $response->getBody()->write("No logs:        {$durationMs} ms (total $total)\n");
    return $response;
});

$app->get('/rolldice-otlp', function (Request $request, Response $response) use ($tracer, $monolog) {
    $startTime = hrtime(true);
    $span = $tracer
        ->spanBuilder('manual-span')
        ->startSpan();
    $span->activate();
    $total = 0;
    for ($i = 0; $i < 10_000; $i++) {
        $ispan = $tracer
            ->spanBuilder('inner-span')
            ->startSpan();
        $total += random_int(1, 6);
        $ispan->end();
    }
    $span->end();

    $durationMs = sprintf("%7.2f", ($endTime = hrtime(true) - $startTime) / 1e6);
    $monolog->info("dice rolled, total: $total");

    $response->getBody()->write("OTLP SDK logs:  {$durationMs} ms (total $total)\n");
    return $response;
});

$app->get('/rolldice-micro', function (Request $request, Response $response) {
    $startTime = hrtime(true);
    $c = new \MicroOTLP\Client("http://localhost:4318");
    //$c = new \MicroOTLP\Client("file://./otlp-data/file.json");
    $span = $c->startSpan("manual-span");
    $total = 0;
    for ($i = 0; $i < 10_000; $i++) {
        $ispan = $c->startSpan('inner-span');
        $total += random_int(1, 6);
        $ispan->end();
    }
    $span->end();

    $durationMs = sprintf("%7.2f", (hrtime(true) - $startTime) / 1e6);
    $c->logMessage("dice rolled, total $total");

    $response->getBody()->write("MicroOTLP logs: {$durationMs} ms (total $total)\n");
    $c->flush();
    return $response;
});

$app->run();
