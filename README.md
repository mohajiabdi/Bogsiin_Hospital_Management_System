# Bogsiin_Hospital_Management_System

🏥 Hospital Management System

A modern, role-based Hospital Management System designed to manage patients, employees, appointments, prescriptions, billing, and reports in a clean and secure workflow.
This project was developed as a university system project with real-world structure and best practices.

📌 Project Overview

The Hospital Management System provides a centralized platform for hospital staff to manage daily operations efficiently.
It focuses on simplicity, security, and usability while following realistic hospital workflows.

Key goals:

Organize hospital data in one system

Reduce manual paperwork

Improve appointment and billing management

Apply role-based access control

🚀 Features
🔐 Authentication & Authorization

Secure login and signup

Password hashing

Role-based access (Admin, Staff, Receptionist)

Account activation / deactivation

🧑‍⚕️ Patients Management

Register new patients

View and manage patient records

Track registration dates and details

📅 Appointments

Schedule appointments

Assign doctors/employees

Track appointment status (Pending, Completed, Cancelled)

💊 Prescriptions

Create and manage prescriptions

Track prescribing staff

View prescription history

🧾 Billing & Invoices

Generate bills

Fixed consultation fee support

Surgery cost fetched from database

Payment status tracking (Paid / Unpaid)

Receipt generation

📊 Reports (Admin Only)

Income reports

Appointments statistics

Patient registration trends

Staff overview

Export and print statements

👥 Users & Roles (Admin)

Create, update, and delete users

Assign roles

Enable or disable accounts

🛠️ Technologies Used

Frontend:

HTML5

Tailwind CSS

JavaScript (Vanilla)

Backend:

PHP (PDO)

Database:

MySQL / MariaDB

Other Tools:

XAMPP

Git & GitHub

🗂️ Project Structure
hospital/
│── assets/
│── auth/
│   ├── login.php
│   ├── signup.php
│── includes/
│   ├── db.php
│   ├── auth_guard.php
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│── patients/
│── employees/
│── appointments/
│── prescriptions/
│── billing/
│── reports/
│── users/
│── index.php
│── dashboard.php

⚙️ Installation & Setup

Clone the repository:

git clone https://github.com/your-username/hospital-management-system.git


Move the project to your XAMPP htdocs folder:

C:\xampp\htdocs\hospital


Start Apache and MySQL from XAMPP.

Import the database:

Open phpMyAdmin

Create a database (e.g. hospital_db)

Import the provided .sql file

Configure database connection:

Edit includes/db.php with your DB credentials.

Open in browser:

http://localhost/hospital

🔑 Default Access

The first registered user becomes ADMIN

Subsequent users are created as STAFF by default

Admin can manage users and permissions

👨‍💻 Project Team (Group)

This project was developed collaboratively by a group of programmers:

C1221277 – Mohamed Mahad Abdi

C1220186 – Najma Mohamud Abdulle

C1220204 – Mohamed Hassan Ahmed

C1221155 – Duale Mohamed Ali

C1221298 – Maryan Mohamed Abdullahi

All members contributed to system design, development, and testing using modern web technologies.

📌 Notes

This system is designed following real hospital workflows

Patient data access is controlled by roles

Security and clean UI were prioritized

Suitable for academic and learning purposes, and extendable for real deployment

📜 License

This project is developed for educational purposes.
You are free to study, modify, and improve it.
