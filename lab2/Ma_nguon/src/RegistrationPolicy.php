<?php
declare(strict_types=1);
final class RegistrationPolicy {
    public function decide(int $capacity, int $registered,
        bool $open, bool $waitingEnabled): string {
        // TODO: invalid -> rejected; closed -> rejected;
        // available -> confirmed; full -> waiting/rejected.
        return 'TODO';
    }
}
