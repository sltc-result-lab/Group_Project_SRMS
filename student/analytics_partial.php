<?php
// Extracted analytics section

$grade_counts_to_use = $is_all_semesters ? $overall_grade_counts : $grade_counts;
$strongest_subj = $is_all_semesters ? $overall_strongest_subject : $strongest_subject;
$highest_m = $is_all_semesters ? $overall_highest_mark : $highest_mark;
$weakest_subj = $is_all_semesters ? $overall_weakest_subject : $lowest_subject;
$lowest_m = $is_all_semesters ? $overall_lowest_mark : $lowest_mark;
?>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card card-clean h-100">
            <div class="section-title"><?php echo $is_all_semesters ? 'Semester-wise Average Marks' : 'Subject-wise Marks Chart'; ?></div>
            <div class="card-body p-4">
                <canvas id="marksChart" height="120"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card card-clean h-100">
            <div class="section-title">GPA Progress Chart</div>
            <div class="card-body p-4">
                <canvas id="gpaChart" height="260"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card card-clean h-100">
            <div class="section-title">Grade Summary</div>
            <div class="card-body p-4">
                <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                    <?php foreach($grade_counts_to_use as $g => $count): ?>
                        <div class="text-center p-2 border rounded bg-white shadow-sm" style="min-width: 60px; border-color: #e5e7eb !important;">
                            <div class="fs-4 fw-bold text-primary"><?php echo $count; ?></div>
                            <div class="text-muted small fw-bold"><?php echo $g; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card card-clean h-100">
            <div class="section-title">Performance Insights</div>
            <div class="card-body p-3 d-flex flex-column justify-content-center">
                <?php if ($is_all_semesters): ?>
                    <div class="p-2 mb-2 rounded" style="background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;">
                        <div class="small text-muted mb-1"><i class="fa fa-trophy"></i> Best Semester</div>
                        <strong><?php echo $best_semester; ?></strong> (GPA: <?php echo $highest_sem_gpa == -1 ? 'N/A' : number_format($highest_sem_gpa, 2); ?>)
                    </div>
                    <div class="p-2 mb-2 rounded" style="background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7;">
                        <div class="small text-muted mb-1"><i class="fa fa-exclamation-triangle"></i> Weakest Semester</div>
                        <strong><?php echo $weakest_semester; ?></strong> (GPA: <?php echo $lowest_sem_gpa == 5 ? 'N/A' : number_format($lowest_sem_gpa, 2); ?>)
                    </div>
                <?php endif; ?>

                <?php if (!$is_all_semesters): ?>
                    <div class="p-2 mb-2 rounded" style="background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc;">
                        <div class="small text-muted mb-1"><i class="fa fa-arrow-up"></i> Strongest Subject</div>
                        <strong><?php echo htmlspecialchars($strongest_subj); ?></strong> 
                        <?php if($highest_m != -1) echo "({$highest_m})"; ?>
                    </div>
                    <div class="p-2 mb-2 rounded" style="background-color: #f8d7da; color: #842029; border: 1px solid #f5c2c7;">
                        <div class="small text-muted mb-1"><i class="fa fa-arrow-down"></i> Lowest Subject</div>
                        <strong><?php echo htmlspecialchars($weakest_subj); ?></strong> 
                        <?php if($lowest_m != 101) echo "({$lowest_m})"; ?>
                    </div>
                <?php endif; ?>
                <div class="p-2 mb-0 rounded" style="background-color: #cff4fc; color: #055160; border: 1px solid #b6effb;">
                    <div class="small text-muted mb-1"><i class="fa fa-line-chart"></i> GPA Trend</div>
                    <strong>Your GPA is <?php echo $gpa_trend_msg; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
<?php 
$js_labels = $is_all_semesters ? $trend_labels : $chart_labels;
$js_marks = $is_all_semesters ? $trend_averages : $chart_marks;
$marks_label = $is_all_semesters ? 'Average Marks' : 'Marks';
?>
const marksCtx = document.getElementById('marksChart').getContext('2d');
new Chart(marksCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($js_labels); ?>,
        datasets: [{
            label: '<?php echo $marks_label; ?>',
            data: <?php echo json_encode($js_marks); ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.8)',
            borderColor: 'rgba(59, 130, 246, 1)',
            borderWidth: 1,
            borderRadius: 2
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});

const gpaCtx = document.getElementById('gpaChart').getContext('2d');
new Chart(gpaCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($trend_labels); ?>,
        datasets: [
            {
                type: 'line',
                label: 'GPA Trend',
                data: <?php echo json_encode($trend_gpas); ?>,
                fill: false,
                tension: 0.4,
                borderWidth: 2,
                borderColor: '#2ecc71',
                backgroundColor: '#ffffff',
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#2ecc71',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            },
            {
                type: 'bar',
                label: 'GPA',
                data: <?php echo json_encode($trend_gpas); ?>,
                backgroundColor: 'rgba(46, 204, 113, 0.6)',
                borderRadius: 4,
                barPercentage: 0.5
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                max: 4
            }
        }
    }
});
</script>
