<?php

declare(strict_types=1);

namespace Illuminate\Foundation\Auth\Access {
    trait AuthorizesRequests {}
}

namespace Illuminate\Foundation\Validation {
    trait ValidatesRequests {}
}

namespace Illuminate\Routing {
    class Controller {}
}

namespace {
    use App\Data\SalesKpi\SalesKpiFilter;
    use App\Exceptions\SalesKpiDataException;
    use App\Http\Controllers\SalesKpiController;

    spl_autoload_register(static function (string $class): void {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }

        $path = __DIR__.'/../app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (is_file($path)) {
            require $path;
        }
    });

    $controller = new SalesKpiController();
    $method = new ReflectionMethod($controller, 'friendlyError');
    $method->setAccessible(true);
    $filter = SalesKpiFilter::from('2026-07-20');

    $oneFailure = $method->invoke(
        $controller,
        $filter,
        SalesKpiDataException::forSource('SMI', 'password=secret SQLSTATE stack trace'),
    );
    $expectedOne = "Laporan KPI gabungan tidak dapat dimuat.\n\nKoneksi ke database AS_SMI gagal.\nData database lainnya tidak ditampilkan agar hasil gabungan tidak menyesatkan.";
    if ($oneFailure !== $expectedOne) {
        throw new RuntimeException('One-source combined failure message is not deterministic.');
    }

    $bothFailure = $method->invoke(
        $controller,
        $filter,
        SalesKpiDataException::forSources(['INGCO', 'SMI'], 'password=secret SQLSTATE stack trace'),
    );
    if (!str_contains($bothFailure, 'AS_INGCO dan AS_SMI') || str_contains($bothFailure, 'password')) {
        throw new RuntimeException('Two-source failure message is unsafe or incomplete.');
    }

    $missingColumn = 'Kolom StatusClearanceSale tidak ditemukan pada hasil fn_PenjualanLini.';
    $columnFailure = $method->invoke(
        $controller,
        $filter,
        SalesKpiDataException::forSource('INGCO', $missingColumn),
    );
    if ($columnFailure !== $missingColumn) {
        throw new RuntimeException('Missing-column message must be preserved exactly.');
    }

    echo "manual_sales_kpi_errors: OK\n";
}
