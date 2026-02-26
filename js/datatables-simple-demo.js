window.addEventListener('DOMContentLoaded', event => {
    // Simple-DataTables
    // https://github.com/fiduswriter/Simple-DataTables/wiki

    const datatablesSimple = document.getElementById('datatablesSimple');
    if (datatablesSimple) {
        new simpleDatatables.DataTable(datatablesSimple);
    }


    const newLocal = 'my_new_table';
    const my_new_table = document.getElementById(newLocal);

    if (my_new_table) {

        new simpleDatatables.DataTable(my_new_table);

    }

});
