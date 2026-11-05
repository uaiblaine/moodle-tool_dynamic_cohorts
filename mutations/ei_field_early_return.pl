# EI: Validation without a field goes on to read the missing keys.
s{            \$errors\[\'fieldgroup\'\] \= get_string\(\'pleaseselectfield\'\, \'tool_dynamic_cohorts\'\)\;\n            return \$errors\;\n}{            \$errors['fieldgroup'] = get_string('pleaseselectfield', 'tool_dynamic_cohorts');\n}s;
