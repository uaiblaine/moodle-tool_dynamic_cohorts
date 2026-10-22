# AZ: no catch around the scoped config handling.
s{            \} catch \(\\Throwable \$exception\) \{\n                throw new moodle_exception\('Invalid rule data\. Invalid config data for condition: ' \. \$classname\);\n            \}\n}{            \} finally \{\n            \}\n}s;
