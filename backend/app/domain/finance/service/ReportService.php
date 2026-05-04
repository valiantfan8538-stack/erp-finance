<?php

namespace app\domain\finance\service;

use think\facade\Db;

class ReportService
{
    public function balanceSheet(int $bookId, int $year, int $period): array
    {
        $subjects = $this->getSubjects($bookId);
        $balances = $this->getBalancesYtd($bookId, $year, $period);
        $balanceBySubject = [];
        foreach ($balances as $b) {
            $balanceBySubject[$b['subject_id']] = $b;
        }

        $assets      = $this->calcCategory($subjects, $balanceBySubject, 'asset');
        $liabilities = $this->calcCategory($subjects, $balanceBySubject, 'liability');
        $equity      = $this->calcCategory($subjects, $balanceBySubject, 'equity');

        $assetTotal  = $this->sumItems($assets);
        $liabTotal   = $this->sumItems($liabilities);
        $equityTotal = $this->sumItems($equity);
        $profit      = $this->calcCurrentProfit($subjects, $balanceBySubject);
        $equityTotal = bcadd($equityTotal, $profit, 2);

        return [
            'report_date' => sprintf('%d-%02d', $year, $period),
            'assets'      => ['items' => $assets, 'total' => $assetTotal],
            'liabilities' => ['items' => $liabilities, 'total' => $liabTotal],
            'equity'      => ['items' => $equity, 'total' => $equityTotal, 'profit' => $profit],
            'balanced'    => bccomp($assetTotal, bcadd($liabTotal, $equityTotal, 2), 2) === 0,
        ];
    }

    public function incomeStatement(int $bookId, int $year, int $period): array
    {
        $subjects = $this->getSubjects($bookId);
        $balancesYtd = $this->getBalancesYtd($bookId, $year, $period);
        $balanceBySubject = [];
        foreach ($balancesYtd as $b) {
            $sid = $b['subject_id'];
            if (!isset($balanceBySubject[$sid])) {
                $balanceBySubject[$sid] = $b;
            } else {
                $balanceBySubject[$sid]['debit_occurrence']  = bcadd((string)$balanceBySubject[$sid]['debit_occurrence'], (string)$b['debit_occurrence'], 2);
                $balanceBySubject[$sid]['credit_occurrence'] = bcadd((string)$balanceBySubject[$sid]['credit_occurrence'], (string)$b['credit_occurrence'], 2);
            }
        }

        $revenue = $this->sumCategoryOccurrence($subjects, $balanceBySubject, 'profit', 'credit');
        $cost    = $this->sumCategoryOccurrence($subjects, $balanceBySubject, 'profit', 'debit');
        $profit  = bcsub($revenue, $cost, 2);

        return [
            'year'         => (string)$year,
            'period_range' => "1-{$period}月",
            'revenue'      => $revenue,
            'cost'         => $cost,
            'profit'       => $profit,
        ];
    }

    public function cashFlow(int $bookId, int $year, int $period): array
    {
        $entries = Db::table('finance_voucher_entry')
            ->alias('e')
            ->join('finance_voucher v', 'e.voucher_id = v.id')
            ->join('finance_subject s', 'e.subject_id = s.id')
            ->where('v.book_id', $bookId)
            ->where('v.year', $year)
            ->where('v.period', '<=', $period)
            ->where('v.status', 2)
            ->where('s.is_cash_account', 1)
            ->field('e.debit_amount, e.credit_amount')
            ->select();

        $cashIn  = '0.00';
        $cashOut = '0.00';
        foreach ($entries as $e) {
            $cashIn  = bcadd($cashIn, (string)$e['debit_amount'], 2);
            $cashOut = bcadd($cashOut, (string)$e['credit_amount'], 2);
        }

        return [
            'period'       => sprintf('%d-%02d', $year, $period),
            'cash_inflow'  => $cashIn,
            'cash_outflow' => $cashOut,
            'net_cash_flow'=> bcsub($cashIn, $cashOut, 2),
        ];
    }

    private function calcCategory(array $subjects, array $balanceBySubject, string $category): array
    {
        $items = [];
        $subjectMap = [];
        foreach ($subjects as $s) {
            $subjectMap[$s['id']] = $s;
        }

        foreach ($subjects as $s) {
            if ($s['category'] !== $category || $s['parent_id'] != 0) continue;
            $endBalance = $this->aggregateBalance($s['id'], $subjects, $subjectMap, $balanceBySubject);
            $items[] = ['code' => $s['code'], 'name' => $s['name'], 'end_balance' => $endBalance];
        }
        return $items;
    }

    private function aggregateBalance(int $subjectId, array $subjects, array $subjectMap, array $balanceBySubject): string
    {
        if (!isset($subjectMap[$subjectId])) return '0.00';

        $subject = $subjectMap[$subjectId];
        $total = '0.00';
        $b = $balanceBySubject[$subjectId] ?? null;
        if ($b) {
            if ($subject['direction'] === 'debit') {
                $total = bcsub((string)$b['final_debit'], (string)$b['final_credit'], 2);
            } else {
                $total = bcsub((string)$b['final_credit'], (string)$b['final_debit'], 2);
            }
        }

        foreach ($subjects as $child) {
            if ($child['parent_id'] == $subjectId) {
                $childBalance = $this->aggregateBalance($child['id'], $subjects, $subjectMap, $balanceBySubject);
                $total = bcadd($total, $childBalance, 2);
            }
        }

        return $total;
    }

    private function sumItems(array $items): string
    {
        $total = '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, $item['end_balance'], 2);
        }
        return $total;
    }

    private function calcCurrentProfit(array $subjects, array $balanceBySubject): string
    {
        $revenue = '0.00'; $cost = '0.00';
        foreach ($subjects as $s) {
            if ($s['category'] !== 'profit') continue;
            $b = $balanceBySubject[$s['id']] ?? null;
            if (!$b) continue;
            if ($s['direction'] === 'credit') {
                $revenue = bcadd($revenue, bcsub((string)$b['credit_occurrence'], (string)$b['debit_occurrence'], 2), 2);
            } else {
                $cost = bcadd($cost, bcsub((string)$b['debit_occurrence'], (string)$b['credit_occurrence'], 2), 2);
            }
        }
        return bcsub($revenue, $cost, 2);
    }

    private function sumCategoryOccurrence(array $subjects, array $balanceBySubject, string $category, string $direction): string
    {
        $total = '0.00';
        foreach ($subjects as $s) {
            if ($s['category'] !== $category) continue;
            $b = $balanceBySubject[$s['id']] ?? null;
            if (!$b) continue;
            $total = bcadd($total, (string)$b[$direction === 'debit' ? 'debit_occurrence' : 'credit_occurrence'], 2);
        }
        return $total;
    }

    private function getSubjects(int $bookId): array
    {
        return Db::table('finance_subject')
            ->where('book_id', $bookId)->where('status', 1)
            ->select()->toArray();
    }

    private function getBalancesYtd(int $bookId, int $year, int $period): array
    {
        return Db::table('finance_subject_balance')
            ->where('book_id', $bookId)
            ->where('year', $year)
            ->where('period', '<=', $period)
            ->select()->toArray();
    }
}
