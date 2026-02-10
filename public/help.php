<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Process contact form
$message_sent = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Simple email sending - in a production environment, you'd want more robust handling
        $to = 'admin@example.com'; // Change this to your support email
        $subject = 'WiFi System Support Request';
        $email_message = "Name: $name\n";
        $email_message .= "Email: $email\n";
        $email_message .= "Phone: $phone\n\n";
        $email_message .= "Message:\n$message";
        $headers = 'From: ' . $email;
        
        // Attempt to send email
        // mail($to, $subject, $email_message, $headers); // Uncomment in production
        
        $message_sent = true;
    }
}

// Replace the direct CSS include and HTML header with this:
$pageTitle = "Help Center";
require_once '../includes/components/header.php';
?>
<!-- Rest of your HTML content -->

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Help & Support</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message_sent): ?>
                            <div class="alert alert-success">
                                <h5 class="alert-heading">Message Sent!</h5>
                                <p>Thank you for contacting us. We will respond to your query as soon as possible.</p>
                                <hr>
                                <p class="mb-0">You can return to the <a href="../index.php">home page</a> or <a href="onboarding.php">continue with your onboarding</a>.</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-lg-6">
                                    <h5>Frequently Asked Questions</h5>
                                    
                                    <div class="accordion mt-3" id="faqAccordion">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingOne">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                                    How do I get my WiFi voucher code?
                                                </button>
                                            </h2>
                                            <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    After completing the onboarding process, your accommodation manager will verify your information. 
                                                    Once approved, you'll receive your first WiFi voucher code via your preferred communication method (SMS or WhatsApp).
                                                    New voucher codes will be sent to you automatically each month.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingTwo">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                                    What is a MAC address and how do I find it?
                                                </button>
                                            </h2>
                                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    A MAC address is a unique identifier assigned to your device's network interface. Here's how to find it:
                                                    <ul>
                                                        <li><strong>On Android:</strong> Go to Settings > About Phone > Status > WiFi MAC Address</li>
                                                        <li><strong>On iPhone:</strong> Go to Settings > General > About > WiFi Address</li>
                                                        <li><strong>On Windows:</strong> Open Command Prompt and type "ipconfig /all" and look for "Physical Address"</li>
                                                        <li><strong>On Mac:</strong> Go to System Preferences > Network > WiFi > Advanced > Hardware</li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingThree">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                                    How many devices can I connect?
                                                </button>
                                            </h2>
                                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    Each voucher code allows you to connect up to 2 devices simultaneously. These should be your personal devices that you use regularly.
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="headingFour">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                                    I didn't receive my voucher. What should I do?
                                                </button>
                                            </h2>
                                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#faqAccordion">
                                                <div class="accordion-body">
                                                    If you haven't received your voucher, please:
                                                    <ol>
                                                        <li>Check that you've completed all steps of the onboarding process</li>
                                                        <li>Verify that your contact details are correct</li>
                                                        <li>Contact your accommodation manager</li>
                                                        <li>Use the contact form on this page if the issue persists</li>
                                                    </ol>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-6 mt-4 mt-lg-0">
                                    <h5>Contact Support</h5>
                                    
                                    <?php if ($error): ?>
                                        <div class="alert alert-danger"><?= $error ?></div>
                                    <?php endif; ?>
                                    
                                    <form method="post" action="" class="mt-3">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Your Name *</label>
                                            <input type="text" class="form-control" id="name" name="name" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email Address *</label>
                                            <input type="email" class="form-control" id="email" name="email" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Phone Number</label>
                                            <input type="tel" class="form-control" id="phone" name="phone">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="message" class="form-label">Message *</label>
                                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary">Send Message</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="onboarding.php" class="btn btn-link">Return to Onboarding</a> | 
                    <a href="../index.php" class="btn btn-link">Back to Home</a>
                </div>
            </div>
        </div>
    </div>

<?php require_once '../includes/components/footer.php'; ?>
