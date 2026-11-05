# DK: Custom profile menu option labels reach descriptions and option lists raw.
s{array_map\(fn\(string \$value\)\: string \=\> format_string\(\$value\, true\, \$formatoptions\)\, \$values\)}{\$values}s;
