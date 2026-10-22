# AY: ignore $CFG->hiddenuserfields.
s{\$hidden = in_array\(\$field, explode\(',', \(string\) \(\$CFG->hiddenuserfields \?\? ''\)\), true\);}{\$hidden = false;}s;
