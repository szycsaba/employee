<?php

class Query {
    private $pdo;

    function __construct() {
        $this->pdo = $this->getConnection();
    }

    private function getConnection() {
        return new PDO("mysql:host=" . $_SERVER["DB_HOST"] . ";dbname=" . $_SERVER["DB_NAME"],
            $_SERVER["DB_USER"],
            $_SERVER["DB_PASSWORD"]
        );
    }

    public function employeeIsValid($empNo) {
        $query = 
        "
            SELECT
                COUNT(*)
            FROM
                employees
            WHERE
                emp_no = ".$empNo."
        ";
        $statement = $this->pdo->query($query);
        $count = $statement->fetchColumn();

        return ($count > 0 ? true : false);
    }

    public function getEmployees() {
        $query = 
        "
            SELECT
                e.*,
                t.title,
                d.dept_name,
                de.dept_no,
                s.salary
            FROM
                employees e
            LEFT JOIN
                ( SELECT emp_no, title, to_date FROM titles t1 WHERE to_date = ( SELECT MAX(t2.to_date) FROM titles t2 WHERE t1.emp_no = t2.emp_no ) ) t
            ON
                t.emp_no = e.emp_no
            LEFT JOIN
                ( SELECT emp_no, dept_no, to_date FROM dept_emp de1 WHERE to_date = ( SELECT MAX(de2.to_date) FROM dept_emp de2 WHERE de1.emp_no = de2.emp_no ) ) de
            ON
                de.emp_no = e.emp_no
            LEFT JOIN
                departments d
            ON
                d.dept_no = de.dept_no
            LEFT JOIN
                (
                    SELECT
                        salA.emp_no,
                        salA.salary,
                        salA.to_date
                    FROM
                        salaries salA
                    INNER JOIN
                        (
                            SELECT
                                emp_no,
                                MAX(to_date) as to_date
                            FROM
                                salaries
                            GROUP BY
                                emp_no
                        ) salB
                    ON
                        salA.emp_no = salB.emp_no
                        AND salA.to_date = salB.to_date
                ) s
            ON
                s.emp_no = e.emp_no;
        ";
        
        $statement = $this->pdo->prepare($query);
        $statement->execute();
        $return = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $return;
    }

    public function getEmployeeById($empNo) {
        $query = 
        "
            SELECT
                e.*,
                (
                    CASE WHEN
                        gender = 'M'
                    THEN
                        'Male'
                    ELSE
                        'Female'
                    END
                ) as male,
                t.title,
                d.dept_name,
                de.dept_no,
                s.salary
            FROM
                employees e
            LEFT JOIN
                ( SELECT emp_no, title, to_date FROM titles t1 WHERE to_date = ( SELECT MAX(t2.to_date) FROM titles t2 WHERE t1.emp_no = t2.emp_no ) ) t
            ON
                t.emp_no = e.emp_no
            LEFT JOIN
                ( SELECT emp_no, dept_no, to_date FROM dept_emp de1 WHERE to_date = ( SELECT MAX(de2.to_date) FROM dept_emp de2 WHERE de1.emp_no = de2.emp_no ) ) de
            ON
                de.emp_no = e.emp_no
            LEFT JOIN
                departments d
            ON
                d.dept_no = de.dept_no
            LEFT JOIN
                (
                    SELECT
                        salA.emp_no,
                        salA.salary,
                        salA.to_date
                    FROM
                        salaries salA
                    INNER JOIN
                        (
                            SELECT
                                emp_no,
                                MAX(to_date) as to_date
                            FROM
                                salaries
                            GROUP BY
                                emp_no
                        ) salB
                    ON
                        salA.emp_no = salB.emp_no
                        AND salA.to_date = salB.to_date
                ) s
            ON
                s.emp_no = e.emp_no
            WHERE
                e.emp_no = :empNo
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $empNo, PDO::PARAM_INT);
        $statement->execute();
        $return = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $return;
    }

    public function getTitles() {
        $query = 
        "
            SELECT DISTINCT
                title
            FROM
                titles
            ORDER BY
                title ASC
        ";
        $statement = $this->pdo->prepare($query);
        $statement->execute();
        $return = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $return;
    }

    public function getDepartments() {
        $query = 
        "
            SELECT
                dept_no,
                dept_name
            FROM
                departments
            ORDER BY
                dept_name ASC
            
        ";
        $statement = $this->pdo->prepare($query);
        $statement->execute();
        $return = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $return;
    }

    public function saveEmployee($employeeData) {
        // Update employee data
        $query =
        "
            UPDATE
                employees e
            SET
                e.first_name = :firstName,
                e.last_name = :lastName,
                e.hire_date = :hireDate
            WHERE
                e.emp_no = :empNo;
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':firstName', $employeeData['firstName'], PDO::PARAM_STR);
        $statement->bindParam(':lastName', $employeeData['lastName'], PDO::PARAM_STR);
        $statement->bindParam(':hireDate', $employeeData['hireDate'], PDO::PARAM_STR);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->execute();

        // Update the previously used title's to_date of employee
        $query = 
        "
            UPDATE
                titles t
            JOIN
                (
                    SELECT
                        emp_no,
                        MAX(to_date) as to_date
                    FROM
                        titles
                    GROUP BY
                        emp_no
                ) tit
            ON
                tit.emp_no = t.emp_no
            SET
                t.to_date = CURRENT_DATE
            WHERE
                t.emp_no = :empNo
                AND t.to_date = tit.to_date;
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->execute();

        // Add the new one
        $query = 
        "
            INSERT INTO
                titles(emp_no, title, from_date, to_date)
            VALUES
                (:empNo, :title, CURRENT_DATE, '9999-01-01');
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->bindParam(':title', $employeeData['title'], PDO::PARAM_STR);
        $statement->execute();

        // Update the previously used department
        $query = 
        "
            UPDATE
                dept_emp de
            JOIN
                (
                    SELECT
                        emp_no,
                        MAX(to_date) as to_date
                    FROM
                        dept_emp
                    GROUP BY
                        emp_no
                ) dept
            ON
                de.emp_no = dept.emp_no
            SET
                de.to_date = CURRENT_DATE
            WHERE
                de.emp_no = :empNo
                AND de.to_date = dept.to_date
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->execute();

        // Add the new one
        $query = 
        "
            INSERT INTO
                dept_emp(emp_no, dept_no, from_date, to_date)
            VALUES
                (:empNo, :department, CURRENT_DATE, '9999-01-01');
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->bindParam(':department', $employeeData['department'], PDO::PARAM_STR);
        $statement->execute();

        // Update the previously used salary
        $query = 
        "
            UPDATE
                salaries s
            JOIN
                (
                    SELECT
                        emp_no,
                        MAX(to_date) as to_date
                    FROM
                        salaries
                    GROUP BY
                        emp_no
                ) sal
            ON
                s.emp_no = sal.emp_no
            SET
                s.to_date = CURRENT_DATE
            WHERE
                s.emp_no = :empNo
                AND s.to_date = sal.to_date
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->execute();

        // Add the new one
        $query = 
        "
            INSERT INTO
                salaries(emp_no, salary, from_date, to_date)
            VALUES
                (:empNo, :salary, CURRENT_DATE, '9999-01-01');
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $employeeData['emp_no'], PDO::PARAM_INT);
        $statement->bindParam(':salary', $employeeData['salary'], PDO::PARAM_STR);
        $statement->execute();
    }

    public function deleteEmployee($id) {
        // Add the new one
        $query = 
        "
            DELETE FROM
                employees
            WHERE
                emp_no = :empNo
        ";
        $statement = $this->pdo->prepare($query);
        $statement->bindParam(':empNo', $id, PDO::PARAM_INT);
        $statement->execute();
    }

}