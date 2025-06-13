<?php
session_start();
require_once 'db.php';

// Initialize error message variable
$error_message = '';

// Handle user authentication
if (isset($_POST['signup'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    $sql = "INSERT INTO users (username, email, password, role) VALUES ('$username', '$email', '$password', '$role')";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['user_id'] = mysqli_insert_id($conn);
        $_SESSION['role'] = $role;
    } else {
        $error_message = "Signup failed: " . mysqli_error($conn);
    }
}

if (isset($_POST['login'])) {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
        } else {
            $error_message = "Invalid password.";
        }
    } else {
        $error_message = "Invalid email.";
    }
}

// Handle gig creation
if (isset($_POST['create_gig']) && isset($_SESSION['role']) && $_SESSION['role'] == 'freelancer') {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $user_id = $_SESSION['user_id'];
    $sql = "INSERT INTO gigs (user_id, title, description, category, price) VALUES ('$user_id', '$title', '$description', '$category', '$price')";
    if (!mysqli_query($conn, $sql)) {
        $error_message = "Gig creation failed: " . mysqli_error($conn);
    }
}

// Handle order placement
if (isset($_POST['place_order']) && isset($_SESSION['role']) && $_SESSION['role'] == 'buyer') {
    $gig_id = mysqli_real_escape_string($conn, $_POST['gig_id']);
    $buyer_id = $_SESSION['user_id'];
    $sql = "INSERT INTO orders (gig_id, buyer_id, status) VALUES ('$gig_id', '$buyer_id', 'pending')";
    if (!mysqli_query($conn, $sql)) {
        $error_message = "Order placement failed: " . mysqli_error($conn);
    }
}

// Handle messaging
if (isset($_POST['send_message'])) {
    if (!isset($_SESSION['user_id'])) {
        $error_message = "You must be logged in to send messages.";
    } elseif (empty($_POST['receiver_id']) || empty($_POST['message'])) {
        $error_message = "Receiver ID and message are required.";
    } else {
        $sender_id = $_SESSION['user_id'];
        $receiver_id = mysqli_real_escape_string($conn, $_POST['receiver_id']);
        $message = mysqli_real_escape_string($conn, $_POST['message']);
        
        // Verify receiver_id exists
        $sql = "SELECT id FROM users WHERE id = '$receiver_id'";
        $result = mysqli_query($conn, $sql);
        if ($result && mysqli_num_rows($result) > 0) {
            $sql = "INSERT INTO messages (sender_id, receiver_id, message) VALUES ('$sender_id', '$receiver_id', '$message')";
            if (mysqli_query($conn, $sql)) {
                $error_message = "Message sent successfully.";
            } else {
                $error_message = "Failed to send message: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Invalid receiver ID.";
        }
    }
}

// Fetch gigs for display
$search = isset($_POST['search']) ? mysqli_real_escape_string($conn, $_POST['search']) : '';
$category_filter = isset($_POST['category']) ? mysqli_real_escape_string($conn, $_POST['category']) : '';
$price_min = isset($_POST['price_min']) ? mysqli_real_escape_string($conn, $_POST['price_min']) : 0;
$price_max = isset($_POST['price_max']) ? mysqli_real_escape_string($conn, $_POST['price_max']) : 1000;

$sql = "SELECT g.*, u.username FROM gigs g JOIN users u ON g.user_id = u.id WHERE 1=1";
if ($search) $sql .= " AND g.title LIKE '%$search%'";
if ($category_filter) $sql .= " AND g.category='$category_filter'";
$sql .= " AND g.price BETWEEN $price_min AND $price_max";
$gigs = mysqli_query($conn, $sql);
if (!$gigs) {
    $error_message = "Failed to fetch gigs: " . mysqli_error($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiverr Clone</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f5f5f5;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background-color: #1dbf73;
            color: white;
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        header h1 {
            display: inline-block;
            margin-left: 20px;
        }

        nav {
            float: right;
            margin-right: 20px;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-weight: bold;
        }

        nav a:hover {
            text-decoration: underline;
        }

        .section {
            display: none;
            margin-top: 80px;
        }

        .section.active {
            display: block;
        }

        .gig-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .gig-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .gig-card:hover {
            transform: translateY(-5px);
        }

        .gig-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .gig-card h3 {
            padding: 10px;
            font-size: 18px;
        }

        .gig-card p {
            padding: 0 10px 10px;
            color: #777;
        }

        .gig-card .price {
            padding: 10px;
            color: #1dbf73;
            font-weight: bold;
        }

        form {
            margin: 20px 0;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        input, select, textarea, button {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            background-color: #1dbf73;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #17a05d;
        }

        .messages {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 20px;
        }

        .message {
            background: #f0f0f0;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
        }

        .error {
            color: red;
            margin: 10px 0;
        }

        .success {
            color: green;
            margin: 10px 0;
        }

        @media (max-width: 768px) {
            .gig-grid {
                grid-template-columns: 1fr;
            }

            nav {
                float: none;
                text-align: center;
            }

            nav a {
                display: block;
                margin: 10px 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Fiverr Clone</h1>
            <nav>
                <a href="#" onclick="showSection('home')">Home</a>
                <a href="#" onclick="showSection('create-gig')">Create Gig</a>
                <a href="#" onclick="showSection('orders')">Orders</a>
                <a href="#" onclick="showSection('messages')">Messages</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="#" onclick="logout()">Logout</a>
                <?php else: ?>
                    <a href="#" onclick="showSection('signup')">Signup</a>
                    <a href="#" onclick="showSection('login')">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="container">
        <?php if ($error_message): ?>
            <p class="<?php echo strpos($error_message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($error_message); ?>
            </p>
        <?php endif; ?>

        <!-- Home Section -->
        <div id="home" class="section active">
            <h2>Find Services</h2>
            <form method="POST">
                <input type="text" name="search" placeholder="Search gigs..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="category">
                    <option value="">All Categories</option>
                    <option value="Graphic Design">Graphic Design</option>
                    <option value="Writing">Writing</option>
                    <option value="Programming">Programming</option>
                </select>
                <input type="number" name="price_min" placeholder="Min Price" value="<?php echo htmlspecialchars($price_min); ?>">
                <input type="number" name="price_max" placeholder="Max Price" value="<?php echo htmlspecialchars($price_max); ?>">
                <button type="submit">Search</button>
            </form>
            <div class="gig-grid">
                <?php while ($gig = mysqli_fetch_assoc($gigs)): ?>
                    <div class="gig-card">
                        <img src="https://via.placeholder.com/150" alt="Gig Image">
                        <h3><?php echo htmlspecialchars($gig['title']); ?></h3>
                        <p>by <?php echo htmlspecialchars($gig['username']); ?></p>
                        <p><?php echo htmlspecialchars($gig['description']); ?></p>
                        <div class="price">$<?php echo htmlspecialchars($gig['price']); ?></div>
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'buyer'): ?>
                            <form method="POST">
                                <input type="hidden" name="gig_id" value="<?php echo $gig['id']; ?>">
                                <button type="submit" name="place_order">Order Now</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>

        <!-- Signup Section -->
        <div id="signup" class="section">
            <h2>Signup</h2>
            <form method="POST">
                <input type="text" name="username" placeholder="Username" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="buyer">Buyer</option>
                    <option value="freelancer">Freelancer</option>
                </select>
                <button type="submit" name="signup">Signup</button>
            </form>
        </div>

        <!-- Login Section -->
        <div id="login" class="section">
            <h2>Login</h2>
            <form method="POST">
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="login">Login</button>
            </form>
        </div>

        <!-- Create Gig Section -->
        <div id="create-gig" class="section">
            <h2>Create Gig</h2>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'freelancer'): ?>
                <form method="POST">
                    <input type="text" name="title" placeholder="Gig Title" required>
                    <textarea name="description" placeholder="Description" required></textarea>
                    <select name="category" required>
                        <option value="Graphic Design">Graphic Design</option>
                        <option value="Writing">Writing</option>
                        <option value="Programming">Programming</option>
                    </select>
                    <input type="number" name="price" placeholder="Price" required>
                    <button type="submit" name="create_gig">Create Gig</button>
                </form>
            <?php else: ?>
                <p>Please sign up as a freelancer to create gigs.</p>
            <?php endif; ?>
        </div>

        <!-- Orders Section -->
        <div id="orders" class="section">
            <h2>Your Orders</h2>
            <?php
            if (isset($_SESSION['user_id'])) {
                $user_id = $_SESSION['user_id'];
                $sql = "SELECT o.*, g.title, u.username FROM orders o JOIN gigs g ON o.gig_id = g.id JOIN users u ON g.user_id = u.id WHERE o.buyer_id='$user_id' OR g.user_id='$user_id'";
                $orders = mysqli_query($conn, $sql);
                if ($orders) {
                    while ($order = mysqli_fetch_assoc($orders)): ?>
                        <div class="gig-card">
                            <h3><?php echo htmlspecialchars($order['title']); ?></h3>
                            <p>Status: <?php echo htmlspecialchars($order['status']); ?></p>
                            <p>Freelancer: <?php echo htmlspecialchars($order['username']); ?></p>
                        </div>
                    <?php endwhile;
                } else {
                    echo "<p>Error fetching orders: " . mysqli_error($conn) . "</p>";
                }
            } else {
                echo "<p>Please login to view orders.</p>";
            }
            ?>
        </div>

        <!-- Messages Section -->
        <div id="messages" class="section">
            <h2>Messages</h2>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="messages">
                    <?php
                    $user_id = $_SESSION['user_id'];
                    $sql = "SELECT m.*, u.username FROM messages m JOIN users u ON m.sender_id = u.id WHERE m.sender_id='$user_id' OR m.receiver_id='$user_id'";
                    $messages = mysqli_query($conn, $sql);
                    if ($messages) {
                        while ($message = mysqli_fetch_assoc($messages)): ?>
                            <div class="message">
                                <p><strong><?php echo htmlspecialchars($message['username']); ?>:</strong> <?php echo htmlspecialchars($message['message']); ?></p>
                            </div>
                        <?php endwhile;
                    } else {
                        echo "<p>Error fetching messages: " . mysqli_error($conn) . "</p>";
                    }
                    ?>
                </div>
                <form method="POST">
                    <input type="number" name="receiver_id" placeholder="Receiver ID" required>
                    <textarea name="message" placeholder="Type your message..." required></textarea>
                    <button type="submit" name="send_message">Send</button>
                </form>
            <?php else: ?>
                <p>Please login to send messages.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
        }

        function logout() {
            window.location.href = '?logout=true';
        }

        <?php if (isset($_GET['logout'])): ?>
            <?php session_destroy(); ?>
            window.location.href = '?';
        <?php endif; ?>
    </script>
</body>
</html>
