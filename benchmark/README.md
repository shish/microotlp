MicroOTLP vs OTLP SDK Benchmarks
================================

Run Jaeger as an OTEL collector
```
docker compose up -d
```

Run web app:
```
./run.sh
```

Test endpoints with different logging methods:
```
curl localhost:8080/rolldice-bare localhost:8080/rolldice-otlp localhost:8080/rolldice-micro
```

Check Jaeger UI at http://localhost:16686 to see the traces.
