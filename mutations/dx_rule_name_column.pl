# DX: The rules report prints a rule name raw.
s{                \/\/ The report prints a column\'s value as HTML\.\n                return format_string\(\(string\) \$value\, true\, \[\'context\' \=\> \\core\\context\\system\:\:instance\(\)\]\)\;}{                return (string) \$value;}s;
