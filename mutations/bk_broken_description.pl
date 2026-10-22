# BK: the broken description does not name the reason.
s{(public function get_broken_description\(\): string \{\n)        if \(\$this->is_outside_recorded_scope\(\)\) \{\n            return get_string\('brokenoutofscope', 'tool_dynamic_cohorts'\);\n        \}\n\n}{$1}s;
