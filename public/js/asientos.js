// Mejora progresiva del plano de asientos: muestra el total y respeta el
// máximo por compra. Sin JavaScript el formulario funciona igual y el
// servidor valida todo.
(function () {
    'use strict';

    var form = document.getElementById('form-reserva');
    if (!form) {
        return;
    }

    var precio = parseFloat(form.dataset.precio) || 0;
    var moneda = form.dataset.moneda || '';
    var maximo = parseInt(form.dataset.maximo, 10) || 8;
    var casillas = Array.prototype.slice.call(form.querySelectorAll('input[name="asientos[]"]'));
    var cantidad = document.getElementById('resumen-cantidad');
    var lineaTotal = document.getElementById('resumen-total-linea');
    var total = document.getElementById('resumen-total');

    function actualizar() {
        var elegidas = casillas.filter(function (c) { return c.checked; });
        var n = elegidas.length;

        casillas.forEach(function (c) {
            if (c.dataset.ocupado === undefined) {
                c.dataset.ocupado = c.disabled ? '1' : '0';
            }
            if (c.dataset.ocupado === '0') {
                c.disabled = !c.checked && n >= maximo;
            }
        });

        if (n === 0) {
            cantidad.textContent = 'Elija sus asientos en el plano.';
            lineaTotal.hidden = true;
            return;
        }

        var etiquetas = elegidas.map(function (c) { return c.dataset.etiqueta; }).join(', ');
        cantidad.textContent = n + (n === 1 ? ' asiento: ' : ' asientos: ') + etiquetas +
            (n >= maximo ? ' (máximo alcanzado)' : '');
        total.textContent = (precio * n).toFixed(2) + ' ' + moneda;
        lineaTotal.hidden = false;
    }

    form.addEventListener('change', actualizar);
    actualizar();
}());
