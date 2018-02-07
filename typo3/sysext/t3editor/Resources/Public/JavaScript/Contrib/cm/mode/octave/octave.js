!function(a){"object"==typeof exports&&"object"==typeof module?a(require("../../lib/codemirror")):"function"==typeof define&&define.amd?define(["../../lib/codemirror"],a):a(CodeMirror)}(function(a){"use strict";a.defineMode("octave",function(){function a(a){return new RegExp("^(("+a.join(")|(")+"))\\b")}function b(a,b){return a.sol()||"'"!==a.peek()?(b.tokenize=d,d(a,b)):(a.next(),b.tokenize=d,"operator")}function c(a,b){return a.match(/^.*%}/)?(b.tokenize=d,"comment"):(a.skipToEnd(),"comment")}function d(n,o){if(n.eatSpace())return null;if(n.match("%{"))return o.tokenize=c,n.skipToEnd(),"comment";if(n.match(/^[%#]/))return n.skipToEnd(),"comment";if(n.match(/^[0-9\.+-]/,!1)){if(n.match(/^[+-]?0x[0-9a-fA-F]+[ij]?/))return n.tokenize=d,"number";if(n.match(/^[+-]?\d*\.\d+([EeDd][+-]?\d+)?[ij]?/))return"number";if(n.match(/^[+-]?\d+([EeDd][+-]?\d+)?[ij]?/))return"number"}if(n.match(a(["nan","NaN","inf","Inf"])))return"number";var p=n.match(/^"(?:[^"]|"")*("|$)/)||n.match(/^'(?:[^']|'')*('|$)/);return p?p[1]?"string":"string error":n.match(m)?"keyword":n.match(l)?"builtin":n.match(k)?"variable":n.match(e)||n.match(g)?"operator":n.match(f)||n.match(h)||n.match(i)?null:n.match(j)?(o.tokenize=b,null):(n.next(),"error")}var e=new RegExp("^[\\+\\-\\*/&|\\^~<>!@'\\\\]"),f=new RegExp("^[\\(\\[\\{\\},:=;]"),g=new RegExp("^((==)|(~=)|(<=)|(>=)|(<<)|(>>)|(\\.[\\+\\-\\*/\\^\\\\]))"),h=new RegExp("^((!=)|(\\+=)|(\\-=)|(\\*=)|(/=)|(&=)|(\\|=)|(\\^=))"),i=new RegExp("^((>>=)|(<<=))"),j=new RegExp("^[\\]\\)]"),k=new RegExp("^[_A-Za-z¡-￿][_A-Za-z0-9¡-￿]*"),l=a(["error","eval","function","abs","acos","atan","asin","cos","cosh","exp","log","prod","sum","log10","max","min","sign","sin","sinh","sqrt","tan","reshape","break","zeros","default","margin","round","ones","rand","syn","ceil","floor","size","clear","zeros","eye","mean","std","cov","det","eig","inv","norm","rank","trace","expm","logm","sqrtm","linspace","plot","title","xlabel","ylabel","legend","text","grid","meshgrid","mesh","num2str","fft","ifft","arrayfun","cellfun","input","fliplr","flipud","ismember"]),m=a(["return","case","switch","else","elseif","end","endif","endfunction","if","otherwise","do","for","while","try","catch","classdef","properties","events","methods","global","persistent","endfor","endwhile","printf","sprintf","disp","until","continue","pkg"]);return{startState:function(){return{tokenize:d}},token:function(a,c){var d=c.tokenize(a,c);return"number"!==d&&"variable"!==d||(c.tokenize=b),d},lineComment:"%",fold:"indent"}}),a.defineMIME("text/x-octave","octave")});