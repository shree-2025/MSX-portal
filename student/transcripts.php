<?php
require_once __DIR__ . '/includes/header.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Get student's transcripts
$transcripts = $conn->query("SELECT * FROM transcripts 
                           WHERE student_id = $userId 
                           ORDER BY created_at DESC");
?>

<div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="md:flex md:items-center md:justify-between mb-8">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl sm:truncate">
                    <i class="fas fa-scroll text-indigo-600 mr-2"></i> My Transcripts
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    View and download your academic transcripts.
                </p>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="rounded-md bg-green-50 p-4 mb-6 shadow-sm border border-green-200">
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

        <?php if ($transcripts->num_rows > 0): ?>
            <div class="bg-white shadow overflow-hidden sm:rounded-lg border border-gray-200">
                <ul class="divide-y divide-gray-200">
                    <?php while ($transcript = $transcripts->fetch_assoc()): 
                        $transcriptNumber = basename($transcript['file_path'], '.pdf');
                    ?>
                    <li class="px-6 py-4 hover:bg-gray-50 transition-colors duration-150">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <i class="fas fa-scroll text-indigo-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        Academic Transcript
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Issued on <?= date('F j, Y', strtotime($transcript['issue_date'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-3">
                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?= $transcript['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ucfirst($transcript['status']) ?>
                                </span>
                                <div class="flex space-x-2">
                                    <a href="/student/view_transcript.php?id=<?= $transcript['id'] ?>" 
                                       target="_blank"
                                       class="text-indigo-600 hover:text-indigo-900 transition-colors duration-200"
                                       data-tooltip="View Transcript">
                                        <i class="far fa-eye"></i>
                                    </a>
                                    <a href="/student/download_transcript.php?id=<?= $transcript['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-900 transition-colors duration-200"
                                       data-tooltip="Download Transcript">
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="text-center bg-white rounded-lg shadow-sm border border-gray-200 py-12 px-6">
                <div class="mx-auto w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
                    <i class="fas fa-scroll text-3xl text-indigo-600"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-1">No transcripts found</h3>
                <p class="text-gray-500 max-w-md mx-auto mb-6">
                    It looks like you don't have any academic transcripts available yet. Please check back later or contact your administrator if you believe this is an error.
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
