<?php
include(__DIR__ . '/classes/Query.php');

// Szerver globalból kiolvassuk a http kérés methodját és hozzájutunk a kérés útvonalához
$method = $_SERVER["REQUEST_METHOD"];
$parsed = parse_url($_SERVER["REQUEST_URI"]);
$path = $parsed['path'];

// Útvonalak nyilvántartása
$routes =
[
    'GET' => [
        '/' => 'employeeListHandler',
        '/employee' => 'employeeHandler'
    ],
    'POST' => [
        '/employee' => 'employeePostHandler'
    ],
];

/*
    Amennyiben érkezik egy http kérés, akkor a $routes változót,
    mint egyfajta katalógust szeretnénk felhasználni és
    METHOD, valamint útvonal alapján ki szeretnénk kérdezni belőle egy
    handler function-t
*/
$handlerFunction = $routes[$method][$path] ?? "notFoundHandler";
$handlerFunction();

function employeePostHandler()
{
    // Ha nincs megadva az azonosító, akkor visszamegyünk a főolalra
    if( !isset($_POST['emp_no']) ) {
        header('Location: /');
    }

    // Megnézzük, hogy létezik e ezzel az azonosítóval alkalmazott
    $query = new Query();
    $isValid = $query->employeeIsValid($_POST['emp_no']);

    // Ha nem létezik átirányítjuk a főoldalra
    if(!$isValid) {
        header('Location: /');
    }

    // Megnézzük mire kattintott
    if(isset($_POST['save'])) {
        // Ha a mentést választotta
        $employee = array();
        $employee['emp_no'] = (int) $_POST['emp_no'];
        $employee['firstName'] = $_POST['firstName'];
        $employee['lastName'] = $_POST['lastName'];
        $employee['hireDate'] = $_POST['hireDate'];
        $employee['title'] = $_POST['title'];
        $employee['department'] = $_POST['department'];
        $employee['salary'] = (int) $_POST['salary'];

        $query = new Query();
        $query->saveEmployee($employee);

        header('Location: /employee?id=' . $_POST['emp_no']);

    } else if(isset($_POST['delete'])) {
        // Ha a törlésre nyomott
        $query = new Query();
        $query->deleteEmployee((int) $_POST['emp_no']);

        header('Location: /');
    } else {
        header('Location: /');
    }
}

function employeeHandler()
{

    // Ha nincs id, akkor átirányítjuk a főoldalra
    if( !isset($_GET['id']) || empty($_GET['id']) ) {
        header('Location: /');
    }
    // Megnézzük, hogy létezik e ezzel az azonosítóval alkalmazott
    $query = new Query();
    $isValid = $query->employeeIsValid($_GET['id']);

    // Ha nem létezik átirányítjuk a főoldalra
    if(!$isValid) {
        header('Location: /');
    }
    
    // Alkalmazott adatai
    $employee = $query->getEmployeeById($_GET['id']);
    // Alkalmazott pozíció
    $titles = $query->getTitles();
    // Alkalmazott osztálya
    $departments = $query->getDepartments();

    echo compileTemplate("wrapper.phtml", [
        "content" => compileTemplate("employee.phtml", [
            'employee' => $employee,
            'titles' => $titles,
            'departments' => $departments,
        ])
    ]);
}

function employeeListHandler()
{
    $query = new Query();
    $employees = $query->getEmployees();

    echo compileTemplate("wrapper.phtml", [
        "content" => compileTemplate("employeeList.phtml", [
            'employees' => $employees
        ])
    ]);
}

function getConnection()
{
    return new PDO("mysql:host=" . $_SERVER["DB_HOST"] . ";dbname=" . $_SERVER["DB_NAME"],
        $_SERVER["DB_USER"],
        $_SERVER["DB_PASSWORD"]
    );
}

function compileTemplate($filePath, $params = []): string
{
    ob_start();
    // DIR egy konstans érték és mindig annak a könyvtárnak az útvonalát tartalmazza,
    // ahol az adott fájlod van, amiben használod
    require __DIR__ . "/views/" . $filePath; 
    return ob_get_clean();
}

function notFoundHandler()
{
    echo "Oldal nem található";
}
