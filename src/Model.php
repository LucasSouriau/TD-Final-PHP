<?php

class Model
{
    protected $pdo;

    public function __construct(array $config)
    {
        try {
            if ($config['engine'] == 'mysql') {
                $this->pdo = new \PDO(
                    'mysql:dbname='.$config['database'].';host='.$config['host'],
                    $config['user'],
                    $config['password']
                );
                $this->pdo->exec('SET CHARSET UTF8');
            } else {
                $this->pdo = new \PDO(
                    'sqlite:'.$config['file']
                );
            }
        } catch (\PDOException $error) {
            throw new ModelException('Unable to connect to database');
        }
    }

    /**
     * Tries to execute a statement, throw an explicit exception on failure
     */
    protected function execute(\PDOStatement $query, array $variables = array())
    {
        if (!$query->execute($variables)) {
            $errors = $query->errorInfo();
            throw new ModelException($errors[2]);
        }

        return $query;
    }

    /**
     * Inserting a book in the database
     */
    public function insertBook($title, $author, $synopsis, $image, $copies)
    {
        $query = $this->pdo->prepare('INSERT INTO livres (titre, auteur, synopsis, image)
            VALUES (?, ?, ?, ?)');
        $this->execute($query, array($title, $author, $synopsis, $image));
        $bookId = $this->pdo->lastInsertId();

        for ($i = 0; $i < $copies; $i++){
            $query = $this->pdo->prepare('INSERT INTO exemplaires (book_id)
            VALUES (?)');
            $this->execute($query, array($bookId));
        }
    }

    /**
     * Getting all the books
     */
    public function getBooks()
    {
        $query = $this->pdo->prepare('SELECT livres.* FROM livres');

        $this->execute($query);

        return $query->fetchAll();
    }

    /**
     * Getting a book by id
     */
    public function getBook($id)
    {
        $query = $this->pdo->prepare('SELECT l.*, (SELECT count(e.id) FROM exemplaires e JOIN emprunts em ON e.id = em.exemplaire WHERE em.fini = 0 AND e.book_id = l.id) as borrowed FROM livres l WHERE l.id = ?');
        $this->execute($query, array($id));

        return $query->fetch();

    }


    /**
     * Getting all exemplaires by book id
     */
    public function getExemplaires($id)
    {
        $query = $this->pdo->prepare('SELECT e.*, ( SELECT COUNT(em.id) FROM emprunts em WHERE e.id = em.exemplaire AND em.fini = 0 ) as nodispo FROM exemplaires e WHERE e.book_id = ?');
        $this->execute($query, array($id));

        return $query->fetchAll();

    }


    /**
     * Inserting a borrow in the database
     */
    public function insertBorrow($exemp, $name, $fin)
    {
        $query = $this->pdo->prepare('INSERT INTO emprunts (exemplaire, personne, debut, fin)
            VALUES (?, ?, CURDATE(), ?)');
        $this->execute($query, array($exemp, $name, $fin));
    }

    /**
     * Unborrow in the database
     */
    public function unBorrow($id)
    {
        $query = $this->pdo->prepare('UPDATE emprunts SET fini = 1 WHERE fini = 0 AND exemplaire = ?');
        $this->execute($query, array($id));
    }


    /**
     * Getting a book by exemplaire id
     */
    public function getBookExemplaire($exemp)
    {
        $query = $this->pdo->prepare('SELECT e.book_id as book FROM exemplaires e WHERE e.id = ?');
        $this->execute($query, array($exemp));

        return $query->fetch();

    }
}
