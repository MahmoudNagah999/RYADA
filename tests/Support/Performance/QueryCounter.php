<?php

namespace Tests\Support\Performance;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

/**
 * Counts the SQL queries a callback runs, without leaking listeners between tests.
 *
 * `DB::enableQueryLog()` is deliberately not used: the query log is connection state
 * and a forgotten `flushQueryLog()` leaks into the next test. This helper registers a
 * `QueryExecuted` listener, snapshots the listeners already present, and restores them
 * in a `finally` block so one measurement can never change the next one.
 */
final class QueryCounter
{
    private function __construct() {}

    /**
     * @return array{count: int, queries: list<string>}
     */
    public static function measure(callable $callback): array
    {
        $dispatcher = DB::connection()->getEventDispatcher();

        // Listeners are prepared callables (closures or [object, method] arrays).
        // Keeping the previous ones lets us remove ours without dropping anyone else's.
        $previous = $dispatcher instanceof Dispatcher
            ? ($dispatcher->getRawListeners()[QueryExecuted::class] ?? [])
            : [];

        /** @var list<string> $queries */
        $queries = [];

        $listener = static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->toRawSql();
        };

        DB::listen($listener);

        try {
            $callback();
        } finally {
            if ($dispatcher instanceof Dispatcher) {
                $dispatcher->forget(QueryExecuted::class);

                foreach ($previous as $previousListener) {
                    $dispatcher->listen(QueryExecuted::class, $previousListener);
                }
            }
        }

        return [
            'count' => count($queries),
            'queries' => $queries,
        ];
    }

    /**
     * The number of SQL queries the callback ran.
     */
    public static function count(callable $callback): int
    {
        return self::measure($callback)['count'];
    }
}
