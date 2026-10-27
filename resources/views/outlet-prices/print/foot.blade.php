</tbody>
            <tfoot>
                @if ($total === 0)
                    <tr><td colspan="14" class="c muted">Tidak ada produk untuk dicetak.</td></tr>
                @else
                    <tr><td colspan="14" class="r"><strong>Total {{ number_format($total, 0, ',', '.') }} produk</strong></td></tr>
                @endif
            </tfoot>
        </table>
    </div>
</body>

</html>