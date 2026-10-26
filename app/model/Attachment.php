<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 方案附件模型
 * @property int    $id
 * @property int    $proposal_id
 * @property int    $user_id
 * @property string $file_name
 * @property string $file_path
 * @property string $file_ext
 * @property int    $file_size
 */
class Attachment extends Model
{
    /** 附件类型：零件图片 */
    public const TYPE_PART = 'part';
    /** 附件类型：内部排列图 */
    public const TYPE_INNER = 'inner';
    /** 附件类型：整体包装图 */
    public const TYPE_FINAL = 'final';
    /** 附件类型：包装图片 */
    public const TYPE_PACKAGING = 'packaging';
    /** 附件类型：普通附件 */
    public const TYPE_ATTACHMENT = 'attachment';

    /** 附件类型文字 */
    public const TYPE_TEXT = [
        self::TYPE_PART      => '零件图片',
        self::TYPE_INNER     => '内部排列',
        self::TYPE_FINAL     => '整体包装',
        self::TYPE_PACKAGING => '包装图片',
        self::TYPE_ATTACHMENT => '附件',
    ];

    /** 图片类附件类型 */
    public const IMAGE_TYPES = [
        self::TYPE_PART, self::TYPE_INNER, self::TYPE_FINAL, self::TYPE_PACKAGING,
    ];

    /** 允许的图片扩展名 */
    public const IMAGE_EXT = ['png', 'jpg', 'jpeg'];

    /** 图片真实 MIME => 规范扩展名 */
    public const IMAGE_MIME_EXT = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
    ];

    /** 表名 */
    protected $name = 'attachment';

    /** 类型转换 */
    protected $type = [
        'proposal_id' => 'integer',
        'user_id'     => 'integer',
        'file_size'   => 'integer',
    ];

    /** 输出追加字段 */
    protected $append = ['file_size_text', 'is_previewable', 'attach_type_text'];

    /**
     * 附件类型文字
     * @param mixed $value
     * @param array $data
     * @return string
     */
    public function getAttachTypeTextAttr($value, array $data): string
    {
        return self::TYPE_TEXT[(string) ($data['attach_type'] ?? '')] ?? '附件';
    }

    /**
     * 校验文件是否为受支持的图片，并返回真实类型
     * 只认文件内容，不认扩展名，防止改后缀伪装
     * @param string $path
     * @return array{ext:string,mime:string}|null
     */
    public static function detectImage(string $path): ?array
    {
        $info = @getimagesize($path);

        if ($info === false || empty($info['mime'])) {
            return null;
        }

        $mime = (string) $info['mime'];
        $ext  = self::IMAGE_MIME_EXT[$mime] ?? null;

        if ($ext === null) {
            return null;
        }

        return ['ext' => $ext, 'mime' => $mime];
    }

    /**
     * 按类型取附件（图片类，供导出使用）
     * @param int    $proposalId
     * @param string $type
     * @return array<int, string> 绝对路径列表
     */
    public static function imagePathsByType(int $proposalId, string $type): array
    {
        $list = self::where('proposal_id', $proposalId)
            ->where('attach_type', $type)
            ->whereIn('file_ext', self::IMAGE_EXT)
            ->order('id')
            ->select();

        $paths = [];
        foreach ($list as $item) {
            $path = public_path() . str_replace('/', DIRECTORY_SEPARATOR, (string) $item->file_path);

            if (is_file($path)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * 文件大小文字
     * @param mixed $value
     * @param array $data
     * @return string
     */
    public function getFileSizeTextAttr($value, array $data): string
    {
        $size = (int) ($data['file_size'] ?? 0);

        if ($size >= 1024 * 1024) {
            return round($size / 1024 / 1024, 2) . ' MB';
        }
        if ($size >= 1024) {
            return round($size / 1024, 2) . ' KB';
        }

        return $size . ' B';
    }

    /**
     * 是否支持浏览器内预览
     * @param mixed $value
     * @param array $data
     * @return bool
     */
    public function getIsPreviewableAttr($value, array $data): bool
    {
        $ext = strtolower((string) ($data['file_ext'] ?? ''));

        return in_array($ext, ['pdf', 'png', 'jpg'], true);
    }

    /**
     * 关联：所属方案
     * @return BelongsTo
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(PackProposal::class, 'proposal_id');
    }

    /**
     * 关联：上传人
     * @return BelongsTo
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->field('id,username,real_name,role,status');
    }
}
