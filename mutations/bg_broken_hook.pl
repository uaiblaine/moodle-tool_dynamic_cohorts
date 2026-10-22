# BG-BJ: is_broken() ignores the recorded scope.
s{(public function is_broken\(\): bool \{\n)        if \(\$this->is_outside_recorded_scope\(\)\) \{\n            return true;\n        \}\n\n}{$1}s;
