# AK: a stored condition sent back unchanged is not checked against the scope.
s{\} else if \(\$scope !== null\) \{\n(\s+// Unchanged is not exempt)}{\} else if (false) \{\n$1}s;
