<?php
namespace Aws\Waiter;

use GuzzleHttp\Event\HasEmitterInterface;
use GuzzleHttp\Event\HasEmitterTrait;

/**
 * A Waiter attempts to execute a given callback 1 or more times until the
 * callback returns `true`. In between attempts the Waiter sleeps for a given
 * `interval`. If the number of attempts exceeds the given `max_attempts`, then
 * the Waiter stops and throws an exception.
 *
 * @emits wait WaitEvent Emitted before the waiter sleeps in between attempts
 */
class Waiter implements HasEmitterInterface
{
    use HasEmitterTrait;

    /** @var array Default configuration options */
    private static $defaults = [
        'delay'        => 0,
        'interval'     => 0,
        'max_attempts' => 3,
    ];

    /** @var array Waiter configuration options */
    private $config;

    /** @var callable Callback for waiting logic */
    private $waitCallback;

    /**
     * @param callable $waitCallback
     * @param array    $config
     */
    public function __construct(callable $waitCallback, array $config = [])
    {
        $this->waitCallback = $waitCallback;
        $this->config = $config + self::$defaults;
    }

    /**
     * @throws \RuntimeException if the max attempts is exceeded
     */
    public function wait()
    {
        $attempts = 0;

        // Perform an initial delay if configured
        if ($this->config['delay']) {
            usleep($this->config['delay'] * 1000000);
        }

        // If not yet reached max attempts, keep trying to perform wait callback
        while ($attempts < $this->config['max_attempts']) {
            // Perform the callback; if true, then waiting is finished
            if (call_user_func($this->waitCallback)) {
                return;
            }

            // Emit a wait event so collaborators can do something between waits
            $event = new WaitEvent($this->config, $attempts);
            $this->getEmitter()->emit('wait', $event);

            // Wait the specified interval
            if ($this->config['interval']) {
                usleep($this->config['interval'] * 1000000);
            }

            $attempts++;
        }

        throw new \RuntimeException("Waiter failed after {$attempts} attempts");
    }
}
