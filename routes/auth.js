const express = require("express");
const router = express.Router();
const db = require("../db");


// SIGNUP (REGISTER USER)
router.post("/signup", (req, res) => {
    const { firstName, lastName, email, password } = req.body;

    const sql = `
        INSERT INTO users (first_name, last_name, email, password)
        VALUES (?, ?, ?, ?)
    `;

    db.query(sql, [firstName, lastName, email, password], (err, result) => {
        if (err) return res.status(500).json(err);

        res.json({
            message: "User registered successfully",
            userId: result.insertId
        });
    });
});


// LOGIN USER
router.post("/login", (req, res) => {
    const { email, password } = req.body;

    const sql = `
        SELECT * FROM users
        WHERE email = ? AND password = ?
    `;

    db.query(sql, [email, password], (err, results) => {
        if (err) return res.status(500).json(err);

        if (results.length > 0) {
            res.json({
                message: "Login successful",
                user: results[0]
            });
        } else {
            res.status(401).json({
                message: "Invalid email or password"
            });
        }
    });
});

module.exports = router;