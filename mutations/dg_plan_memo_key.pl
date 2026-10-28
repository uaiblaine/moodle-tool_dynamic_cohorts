# DG: the learning plan memo ignores the config data it was read for.
s#(public function is_broken\(\): bool \{\n        )\$key = \$this->get_record\(\)->get\('ruleid'\) \. '\|' \. \$this->get_record\(\)->get\('configdata'\);#${1}\$key = '';#s;
