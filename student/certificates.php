<?php
require_once __DIR__ . '/includes/header.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Get student's certificates
$certificates = $conn->query("SELECT c.*, co.title as course_title 
                           FROM certificates c 
                           JOIN courses co ON c.course_id = co.id 
                           WHERE c.user_id = $userId 
                           ORDER BY c.issue_date DESC");
?>

<div class="min-h-screen bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-2xl font-bold text-gray-900">My Certificates</h1>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="rounded-md bg-green-50 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="h-5 w-5 text-green-400 fas fa-check-circle"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">
                            <?= htmlspecialchars(urldecode($_GET['success'])) ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($certificates->num_rows > 0): ?>
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                <?php while ($cert = $certificates->fetch_assoc()): ?>
                    <div class="bg-white overflow-hidden shadow rounded-lg border border-gray-200">
                        <div class="p-5">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-blue-100 rounded-md p-3">
                                    <i class="fas fa-certificate text-blue-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        <?= htmlspecialchars($cert['course_title']) ?>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        Certificate #<?= htmlspecialchars($cert['certificate_number']) ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="mt-4 border-t border-gray-200 pt-4">
                                <dl class="grid grid-cols-1 gap-x-4 gap-y-4">
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Issued On</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <?= date('F j, Y', strtotime($cert['issue_date'])) ?>
                                        </dd>
                                    </div>
                                    <?php if ($cert['expiry_date']): ?>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Expires On</dt>
                                        <dd class="mt-1 text-sm text-gray-900">
                                            <?= date('F j, Y', strtotime($cert['expiry_date'])) ?>
                                        </dd>
                                    </div>
                                    <?php endif; ?>
                                    <div class="sm:col-span-1">
                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                        <dd class="mt-1">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?= $cert['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                <?= ucfirst($cert['status']) ?>
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            
                            <div class="mt-6 flex space-x-3">
                                <a href="<?= htmlspecialchars($cert['file_path']) ?>" 
                                   target="_blank"
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-eye mr-2 color-red-600"></i> View Certificate
                                </a>
                                <a href="<?= htmlspecialchars($cert['file_path']) ?>" 
                                   download
                                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    <i class="fas fa-download mr-2"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-12 border-2 border-dashed border-gray-300 rounded-lg">
                <i class="fas fa-certificate text-4xl text-gray-400 mb-3"></i>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No certificates found</h3>
                <p class="mt-1 text-sm text-gray-500">You don't have any certificates yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
