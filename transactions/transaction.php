<?php
// Include the connection file
include('database/connection.php');

// Initialize variables
$error_message = '';
$success_message = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $account_number = $_POST['account_number'] ?? '';
    $transaction_type = $_POST['transaction_type'] ?? '';
    $amount = $_POST['amount'] ?? 0;
    $description = $_POST['description'] ?? '';

    // Validation
    if (empty($account_number) || empty($transaction_type) || $amount <= 0) {
        $error_message = "Please fill in all required fields and ensure the amount is greater than 0.";
    } else {a
        // Start transaction
        $conn->begin_transaction();

        try {
            // Fetch account balance
            $stmt = $conn->prepare("SELECT balance FROM accounts WHERE account_number = ?");
            $stmt->bind_param("s", $account_number);
            $stmt->execute();
            $result = $stmt->get_result();
            $account = $result->fetch_assoc();

            if (!$account) {
                throw new Exception("Account not found.");
            }

            $balance = $account['balance'];

            // Process transaction
            if ($transaction_type === 'Deposit') {
                $new_balance = $balance + $amount;
            } elseif ($transaction_type === 'Withdrawal') {
                if ($balance < $amount) {
                    throw new Exception("Insufficient funds.");
                }
                $new_balance = $balance - $amount;
            } else {
                throw new Exception("Invalid transaction type.");
            }

            // Update account balance
            $stmt = $conn->prepare("UPDATE accounts SET balance = ? WHERE account_number = ?");
            $stmt->bind_param("ds", $new_balance, $account_number);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update account balance.");
            }

            // Record transaction
            $stmt = $conn->prepare("INSERT INTO transactions (account_number, transaction_type, amount, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssds", $account_number, $transaction_type, $amount, $description);
            if (!$stmt->execute()) {
                throw new Exception("Failed to record transaction.");
            }

            // Commit transaction
            $conn->commit();
            $success_message = "Transaction successful.";
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Transaction</title>
</head>
<body>
    <h1>Bank Transaction</h1>

    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo $error_message; ?></p>
    <?php elseif ($success_message): ?>
        <p style="color: green;"><?php echo $success_message; ?></p>
    <?php endif; ?>

    <form method="post" action="process_transaction.php">
        <label for="account_number">Account Number:</label>
        <input type="text" id="account_number" name="account_number" required><br>

        <label for="transaction_type">Transaction Type:</label>
        <select id="transaction_type" name="transaction_type" required>
            <option value="Deposit">Deposit</option>
            <option value="Withdrawal">Withdrawal</option>
        </select><br>

        <label for="amount">Amount:</label>
        <input type="number" id="amount" name="amount" step="0.01" required><br>

        <label for="description">Description:</label>
        <input type="text" id="description" name="description"><br>

        <button type="submit">Submit</button>
    </form>
</body>
</html>
