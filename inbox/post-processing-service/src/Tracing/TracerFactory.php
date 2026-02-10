<?php

declare(strict_types=1);

namespace PostProcessing\Tracing;

use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;

class TracerFactory
{
    private static ?TracerInterface $tracer = null;
    private static ?TracerProvider $tracerProvider = null;

    public static function create(string $serviceName = 'post-processing'): TracerInterface
    {
        if (self::$tracer !== null) {
            return self::$tracer;
        }

        try {
            $resource = ResourceInfoFactory::emptyResource()->merge(
                ResourceInfo::create(
                    Attributes::create([
                        'service.name' => $serviceName,
                        'service.version' => '1.0.0',
                    ])
                )
            );

            $endpoint = getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'http://jaeger:4318';

            $transportFactory = new OtlpHttpTransportFactory();
            $transport = $transportFactory->create(
                $endpoint . '/v1/traces',
                'application/json',
                [],
                null,
                10.0
            );

            $exporter = new SpanExporter($transport);

            self::$tracerProvider = new TracerProvider(
                new SimpleSpanProcessor($exporter),
                null,
                $resource
            );

            self::$tracer = self::$tracerProvider->getTracer($serviceName, '1.0.0');

            register_shutdown_function([self::class, 'shutdown']);

            return self::$tracer;

        } catch (\Throwable $e) {
            error_log("Failed to initialize OpenTelemetry tracer: " . $e->getMessage());
            self::$tracer = new \OpenTelemetry\API\Trace\NoopTracer();
            return self::$tracer;
        }
    }

    public static function getTracerProvider(): ?TracerProvider
    {
        return self::$tracerProvider;
    }

    public static function shutdown(): void
    {
        if (self::$tracerProvider !== null) {
            self::$tracerProvider->shutdown();
        }
    }
}
