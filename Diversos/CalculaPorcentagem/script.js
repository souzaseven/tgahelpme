document.getElementById('c1v1').addEventListener('input', calcular1);
document.getElementById('c1v2').addEventListener('input', calcular1);
document.getElementById('c2v1').addEventListener('input', calcular2);
document.getElementById('c2v2').addEventListener('input', calcular2);
document.getElementById('c3v1').addEventListener('input', calcular3);
document.getElementById('c3v2').addEventListener('input', calcular3);
document.getElementById('c4v1').addEventListener('input', calcular4);
document.getElementById('c4v2').addEventListener('input', calcular4);
document.getElementById('c5v1').addEventListener('input', calcular5);
document.getElementById('c5v2').addEventListener('input', calcular5);
document.getElementById('c6v1').addEventListener('input', calcular6);
document.getElementById('c6v2').addEventListener('input', calcular6);
document.getElementById('c7v1').addEventListener('input', calcular7);
document.getElementById('c7v2').addEventListener('input', calcular7);
document.getElementById('c8v1').addEventListener('input', calcular8);
document.getElementById('c8v2').addEventListener('input', calcular8);
document.getElementById('c9v1').addEventListener('input', calcular9);
document.getElementById('c9v2').addEventListener('input', calcular9);
document.getElementById('c10v1').addEventListener('input', calcular10);
document.getElementById('c10v2').addEventListener('input', calcular10);
document.getElementById('c11v1').addEventListener('input', calcular11);
document.getElementById('c11v2').addEventListener('input', calcular11);
document.getElementById('c12v1').addEventListener('input', calcular12);
document.getElementById('c12v2').addEventListener('input', calcular12);
document.getElementById('c13v1').addEventListener('input', calcular13);
document.getElementById('c13v2').addEventListener('input', calcular13);

function calcular1() {
    var c1v1 = parseFloat(document.getElementById('c1v1').value);
    var c1v2 = parseFloat(document.getElementById('c1v2').value);
    if (!isNaN(c1v1) && !isNaN(c1v2)) {
        var result1 = (c1v1 / 100) * c1v2;
        document.getElementById('r1').textContent = ' ' + result1.toFixed(2);
    } else {
        document.getElementById('r1').textContent = '';
    }
}

function calcular2() {
    var c2v1 = parseFloat(document.getElementById('c2v1').value);
    var c2v2 = parseFloat(document.getElementById('c2v2').value);
    if (!isNaN(c2v1) && !isNaN(c2v2)) {
        var result2 = (c2v1 / c2v2) * 100;
        document.getElementById('r2').textContent = ' ' + result2.toFixed(2) + '%';
    } else {
        document.getElementById('r2').textContent = '';
    }
}

function calcular3() {
    var c3v1 = parseFloat(document.getElementById('c3v1').value);
    var c3v2 = parseFloat(document.getElementById('c3v2').value);
    if (!isNaN(c3v1) && !isNaN(c3v2)) {
        var result3 = ((c3v2 - c3v1) / c3v1) * 100;
        document.getElementById('r3').textContent = ' ' + result3.toFixed(2) + '%';
    } else {
        document.getElementById('r3').textContent = '';
    }
}

function calcular4() {
    var c4v1 = parseFloat(document.getElementById('c4v1').value);
    var c4v2 = parseFloat(document.getElementById('c4v2').value);
    if (!isNaN(c4v1) && !isNaN(c4v2)) {
        var result4 = c4v1 + (c4v1 * c4v2 / 100);
        document.getElementById('r4').textContent = ' ' + result4.toFixed(2);
    } else {
        document.getElementById('r4').textContent = '';
    }
}

function calcular5() {
    var c5v1 = parseFloat(document.getElementById('c5v1').value);
    var c5v2 = parseFloat(document.getElementById('c5v2').value);
    if (!isNaN(c5v1) && !isNaN(c5v2)) {
        var result5 = c5v1 - (c5v1 * c5v2 / 100);
        document.getElementById('r5').textContent = ' ' + result5.toFixed(2);
    } else {
        document.getElementById('r5').textContent = '';
    }
}

function calcular6() {
    var c6v1 = parseFloat(document.getElementById('c6v1').value);
    var c6v2 = parseFloat(document.getElementById('c6v2').value);
    if (!isNaN(c6v1) && !isNaN(c6v2)) {
        var result6 = c6v1 / (1 + c6v2 / 100);
        document.getElementById('r6').textContent = ' ' + result6.toFixed(2);
    } else {
        document.getElementById('r6').textContent = '';
    }
}

// Calculadora 7 - Múltiplos aumentos
function calcular7() {
    var c7v1 = parseFloat(document.getElementById('c7v1').value);
    var c7v2 = document.getElementById('c7v2').value.split(',').map(parseFloat);
    if (!isNaN(c7v1) && c7v2.every(v => !isNaN(v))) {
        var result7 = c7v2.reduce((acc, p) => acc * (1 + p / 100), c7v1);
        document.getElementById('r7').textContent = ' ' + result7.toFixed(2);
    } else {
        document.getElementById('r7').textContent = '';
    }
}

// Calculadora 8 - Múltiplos descontos
function calcular8() {
    var c8v1 = parseFloat(document.getElementById('c8v1').value);
    var c8v2 = document.getElementById('c8v2').value.split(',').map(parseFloat);
    if (!isNaN(c8v1) && c8v2.every(v => !isNaN(v))) {
        var result8 = c8v2.reduce((acc, p) => acc * (1 - p / 100), c8v1);
        document.getElementById('r8').textContent = ' ' + result8.toFixed(2);
    } else {
        document.getElementById('r8').textContent = '';
    }
}

// Calculadora 9 - Dividir porcentagem entre partes
function calcular9() {
    var c9v1 = parseFloat(document.getElementById('c9v1').value);
    var c9v2 = parseInt(document.getElementById('c9v2').value);
    if (!isNaN(c9v1) && !isNaN(c9v2) && c9v2 > 0) {
        var result9 = c9v1 / c9v2;
        document.getElementById('r9').textContent = ' ' + result9.toFixed(2);
    } else {
        document.getElementById('r9').textContent = '';
    }
}

// Calculadora 10 - Porcentagem de ajuste necessária
function calcular10() {
    var c10v1 = parseFloat(document.getElementById('c10v1').value);
    var c10v2 = parseFloat(document.getElementById('c10v2').value);
    if (!isNaN(c10v1) && !isNaN(c10v2)) {
        var result10 = ((c10v2 - c10v1) / c10v1) * 100;
        document.getElementById('r10').textContent = ' ' + result10.toFixed(2) + '%';
    } else {
        document.getElementById('r10').textContent = '';
    }
}

// Calculadora 11 - Valor original com desconto
function calcular11() {
    var c11v1 = parseFloat(document.getElementById('c11v1').value);
    var c11v2 = parseFloat(document.getElementById('c11v2').value);
    if (!isNaN(c11v1) && !isNaN(c11v2)) {
        var result11 = c11v1 / (1 - c11v2 / 100);
        document.getElementById('r11').textContent = ' ' + result11.toFixed(2);
    } else {
        document.getElementById('r11').textContent = '';
    }
}

// Calculadora 12 - Valor correspondente à porcentagem
function calcular12() {
    var c12v1 = parseFloat(document.getElementById('c12v1').value);
    var c12v2 = parseFloat(document.getElementById('c12v2').value);
    if (!isNaN(c12v1) && !isNaN(c12v2)) {
        var result12 = (c12v1 / 100) * c12v2;
        document.getElementById('r12').textContent = ' ' + result12.toFixed(2);
    } else {
        document.getElementById('r12').textContent = '';
    }
}

// Calculadora 12 - porcentagem correspondente à valor
function calcular13() {
    var c13v1 = parseFloat(document.getElementById('c13v1').value);
    var c13v2 = parseFloat(document.getElementById('c13v2').value);
    
    if (!isNaN(c13v1) && !isNaN(c13v2) && c13v1 !== 0) {
        var result13 = ((c13v2 - c13v1) / c13v1) * 100;
        var texto = result13.toFixed(2) + '%';
        
        if (result13 > 0) {
            texto += ' (aumento)';
        } else if (result13 < 0) {
            texto += ' (desconto)';
        } else {
            texto += ' (sem alteração)';
        }
        
        document.getElementById('r13').textContent = ' ' + texto;
    } else {
        document.getElementById('r13').textContent = '';
    }
}